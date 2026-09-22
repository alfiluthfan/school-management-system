<?php
namespace App\Queries\MasterData;

use App\Authorization\SchoolDataScope;
use App\Enums\Academic\SchoolClassStatus;
use App\Enums\Academic\StudentStatus;
use App\Enums\Academic\TeacherEmploymentStatus;
use App\Enums\Academic\TeacherStatus;
use App\Enums\Common\Gender;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\Teacher;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Support\MasterData\MasterDataCatalog as Catalog;
use Illuminate\Database\Eloquent\Builder;

final class MasterDataPageQuery
{
    public function index(User $actor, string $kind, string $search, int $perPage): array
    {
        $query = match ($kind) {
            'users' => User::query()->with('roles'),
            'students' => SchoolDataScope::students($actor)->with('user'),
            'teachers' => Teacher::query()->with('user'),
            'parents' => Guardian::query()->with('user'),
            'classes' => SchoolDataScope::schoolClasses($actor)->with(['academicYear', 'homeroomTeacher.user']),
            'years' => AcademicYear::query(),
        };
        if ($search !== '') {
            $query->where(function (Builder $q) use ($kind, $search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                match ($kind) {
                    'users' => $q->where('name', 'like', $like)->orWhere('username', 'like', $like)->orWhere('email', 'like', $like),
                    'students' => $q->where('nis', 'like', $like)->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)),
                    'teachers' => $q->where('nip', 'like', $like)->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)),
                    'parents' => $q->whereHas('user', fn (Builder $u) => $u->where('name', 'like', $like)),
                    'classes' => $q->where('code', 'like', $like)->orWhere('name', 'like', $like),
                    'years' => $q->where('name', 'like', $like),
                };
            });
        }
        $paginator = $query->orderByDesc('id')->paginate($perPage)->withQueryString();
        return [
            'records' => $paginator->through(fn ($model): array => $this->serialize($kind, $model, Catalog::canUpdate($actor, $kind))),
            'filters' => ['kind' => $kind, 'search' => $search, 'per_page' => $perPage],
            'tabs' => Catalog::tabs($actor),
            'can' => [
                'create' => Catalog::canCreate($actor, $kind),
                'update' => Catalog::canUpdate($actor, $kind),
                'assign_role' => $actor->hasPermission('role.assign'),
                'activate_user' => $actor->hasPermission('user.activate'),
                'deactivate_user' => $actor->hasPermission('user.deactivate'),
                'reset_password' => $actor->hasPermission('user.password.reset'),
                'activate_year' => $actor->hasPermission('academic-year.activate'),
                'enroll' => $actor->hasPermission('student.update') && $actor->hasPermission('class.view.all'),
                'link_parent' => $actor->hasPermission('parent.update') && $actor->hasPermission('student.view.all'),
            ],
            'options' => $this->options($actor, $kind),
        ];
    }

    public function serialize(string $kind, object $model, bool $allowSensitive = false): array
    {
        $row = match ($kind) {
            'users' => [
                'ref' => $model->uuid, 'title' => $model->name,
                'subtitle' => $model->username.' · '.$model->email,
                'status' => $model->is_active ? 'ACTIVE' : 'INACTIVE',
                'fields' => [
                    'name' => $model->name, 'username' => $model->username,
                    'email' => $model->email, 'phone' => $model->phone,
                    'roles' => $model->roles->pluck('name')->values()->all(),
                ],
            ],
            'students' => [
                'ref' => $model->uuid, 'title' => $model->user?->name ?? 'Akun tidak tersedia',
                'subtitle' => 'NIS: '.$model->nis, 'status' => $this->enumValue($model->status),
                'fields' => [
                    'nis' => $model->nis, 'nisn' => $model->nisn, 'gender' => $this->enumValue($model->gender),
                    'birth_place' => $model->birth_place, 'birth_date' => $model->birth_date?->toDateString(),
                    'address' => $model->address, 'admission_date' => $model->admission_date?->toDateString(),
                    'graduation_date' => $model->graduation_date?->toDateString(), 'status' => $this->enumValue($model->status),
                ],
            ],
            'teachers' => [
                'ref' => $model->uuid, 'title' => $model->user?->name ?? 'Akun tidak tersedia',
                'subtitle' => 'NIP: '.$model->nip, 'status' => $this->enumValue($model->status),
                'fields' => [
                    'nip' => $model->nip, 'employee_number' => $model->employee_number,
                    'gender' => $this->enumValue($model->gender), 'birth_place' => $model->birth_place,
                    'birth_date' => $model->birth_date?->toDateString(), 'address' => $model->address,
                    'employment_status' => $this->enumValue($model->employment_status),
                    'join_date' => $model->join_date?->toDateString(), 'status' => $this->enumValue($model->status),
                ],
            ],
            'parents' => [
                'ref' => $model->uuid, 'title' => $model->user?->name ?? 'Akun tidak tersedia',
                'subtitle' => $model->user?->username ?? '', 'status' => 'REGISTERED',
                'fields' => ['occupation' => $model->occupation, 'address' => $model->address],
            ],
            'classes' => [
                'ref' => $model->uuid, 'title' => $model->name,
                'subtitle' => $model->code.' · '.($model->academicYear?->name ?? '-'),
                'status' => $this->enumValue($model->status),
                'fields' => [
                    'code' => $model->code, 'name' => $model->name,
                    'grade_level' => $model->grade_level, 'major' => $model->major,
                    'status' => $this->enumValue($model->status),
                    'year_ref' => $model->academic_year_id,
                    'teacher_uuid' => $model->homeroomTeacher?->uuid ?? '',
                ],
            ],
            'years' => [
                'ref' => (string) $model->id, 'title' => $model->name,
                'subtitle' => $model->start_date?->toDateString().' – '.$model->end_date?->toDateString(),
                'status' => $model->is_active ? 'ACTIVE' : 'INACTIVE',
                'fields' => [
                    'name' => $model->name, 'start_date' => $model->start_date?->toDateString(),
                    'end_date' => $model->end_date?->toDateString(),
                ],
            ],
        };
        if (!$allowSensitive) {
            $row['fields'] = []; // No DOB, address, email/phone payloads for read-only viewers.
        }
        return $row;
    }

    /** Accept enum-casted models and older models storing raw string values. */
    private function enumValue(\BackedEnum|string|null $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : $value;
    }

    public function options(User $actor, string $kind): array
    {
        $options = [];
        if ($kind === 'users' && $actor->hasPermission('role.assign')) {
            $options['roles'] = Role::query()->orderBy('name')->get(['name', 'display_name'])
                ->map(fn ($role): array => ['value' => $role->name, 'label' => $role->display_name])->all();
        }
        $role = match ($kind) { 'students' => 'student', 'teachers' => 'teacher', 'parents' => 'parent', default => null };
        if ($role && Catalog::canCreate($actor, $kind)) {
            $options['users'] = User::query()->where('is_active', true)
                ->whereHas('roles', fn (Builder $r) => $r->where('name', $role))
                ->whereDoesntHave(match ($kind) { 'students' => 'student', 'teachers' => 'teacher', 'parents' => 'guardian' })
                ->orderBy('name')->limit(250)->get(['id','uuid','name','username'])
                ->map(fn ($u): array => ['value' => $u->uuid, 'label' => $u->name.' ('.$u->username.')'])->all();
        }
        if ($kind === 'classes' && Catalog::canCreate($actor, $kind) ||
            $kind === 'classes' && Catalog::canUpdate($actor, $kind)) {
            $options['years'] = AcademicYear::query()->orderByDesc('start_date')->limit(200)->get(['id','name'])
                ->map(fn ($year): array => ['value' => $year->id, 'label' => $year->name])->all();
            $options['teachers'] = Teacher::query()->where('status', 'ACTIVE')->with('user')
                ->orderBy('nip')->limit(250)->get()->map(fn ($t): array => [
                    'value' => $t->uuid, 'label' => ($t->user?->name ?? $t->nip).' · '.$t->nip,
                ])->all();
        }
        if ($kind === 'students' && $actor->hasPermission('student.update') && $actor->hasPermission('class.view.all')) {
            $options['classes'] = SchoolClass::query()->where('status', 'ACTIVE')->with('academicYear')
                ->orderBy('code')->limit(250)->get()->map(fn ($c): array => [
                    'value' => $c->uuid, 'label' => $c->name.' · '.$c->academicYear?->name,
                ])->all();
        }
        if ($kind === 'parents' && $actor->hasPermission('parent.update') && $actor->hasPermission('student.view.all')) {
            $options['students'] = Student::query()->with('user')->orderBy('nis')->limit(250)->get()
                ->map(fn ($s): array => ['value' => $s->uuid, 'label' => ($s->user?->name ?? '-').' · '.$s->nis])->all();
        }
        return [
            ...$options,
            'genders' => Gender::options(),
            'student_statuses' => StudentStatus::options(),
            'teacher_statuses' => TeacherStatus::options(),
            'employment_statuses' => TeacherEmploymentStatus::options(),
            'class_statuses' => SchoolClassStatus::options(),
        ];
    }
}
