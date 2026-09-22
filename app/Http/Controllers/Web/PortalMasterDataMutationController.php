<?php
namespace App\Http\Controllers\Web;

use App\Actions\MasterData\ManageMasterDataAction;
use App\Enums\Academic\ParentRelationship;
use App\Enums\Academic\SchoolClassStatus;
use App\Enums\Academic\StudentStatus;
use App\Enums\Academic\TeacherEmploymentStatus;
use App\Enums\Academic\TeacherStatus;
use App\Enums\Common\Gender;
use App\Http\Controllers\Controller;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\Teacher;
use App\Models\Auth\User;
use App\Support\MasterData\MasterDataCatalog as Catalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class PortalMasterDataMutationController extends Controller
{
    public function store(Request $request, string $kind, ManageMasterDataAction $action): RedirectResponse
    {
        Catalog::kind($kind);
        abort_unless(Catalog::canCreate($request->user(), $kind), 403);
        $data = $request->validate($this->rules($kind));
        $action->persist($request->user(), $kind, $data);
        return to_route('portal.master-data.index', ['kind' => $kind])->with('success', 'Data berhasil dibuat.');
    }

    public function update(Request $request, string $kind, string $ref,
        ManageMasterDataAction $action): RedirectResponse
    {
        Catalog::kind($kind);
        abort_unless(Catalog::canUpdate($request->user(), $kind), 403);
        $model = $this->find($kind, $ref);
        $data = $request->validate($this->rules($kind, $model));
        if ($kind === 'users' && array_key_exists('roles', $data)) {
            abort_unless($request->user()->hasPermission('role.assign'), 403);
        }
        $action->persist($request->user(), $kind, $data, $model);
        return to_route('portal.master-data.index', ['kind' => $kind])->with('success', 'Data berhasil diperbarui.');
    }

    public function userState(Request $request, User $user, ManageMasterDataAction $action): RedirectResponse
    {
        $active = $request->validate(['active' => ['required', 'boolean']])['active'];
        abort_unless($request->user()->hasPermission($active ? 'user.activate' : 'user.deactivate'), 403);
        $action->setUserActive($request->user(), $user, (bool) $active);
        return to_route('portal.master-data.index', ['kind' => 'users'])
            ->with('success', $active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }

    public function resetPassword(Request $request, User $user, ManageMasterDataAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('user.password.reset'), 403);
        $data = $request->validate([
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
        ]);
        $action->resetPassword($request->user(), $user, $data['password']);
        return to_route('portal.master-data.index', ['kind' => 'users'])
            ->with('success', 'Kata sandi akun berhasil diperbarui. Informasikan lewat kanal aman.');
    }

    public function activateYear(Request $request, AcademicYear $year,
        ManageMasterDataAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('academic-year.activate'), 403);
        $action->activateYear($request->user(), $year);
        return to_route('portal.master-data.index', ['kind' => 'years'])
            ->with('success', 'Tahun ajaran diaktifkan. Tahun ajaran aktif lainnya dinonaktifkan.');
    }

    public function enroll(Request $request, Student $student, ManageMasterDataAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('student.update')
            && $request->user()->hasPermission('class.view.all'), 403);
        $data = $request->validate([
            'class_uuid' => ['required', 'uuid', Rule::exists('school_classes', 'uuid')->whereNull('deleted_at')],
            'joined_at' => ['required', 'date_format:Y-m-d'],
        ]);
        $class = SchoolClass::query()->where('uuid', $data['class_uuid'])->firstOrFail();
        $action->enroll($request->user(), $student, $class, $data['joined_at']);
        return to_route('portal.master-data.index', ['kind' => 'students'])
            ->with('success', 'Enrollment siswa berhasil dicatat.');
    }

    public function linkParent(Request $request, Guardian $guardian,
        ManageMasterDataAction $action): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('parent.update')
            && $request->user()->hasPermission('student.view.all'), 403);
        $data = $request->validate([
            'student_uuid' => ['required', 'uuid', Rule::exists('students', 'uuid')->whereNull('deleted_at')],
            'relationship' => ['required', Rule::in(ParentRelationship::values())],
            'is_primary_contact' => ['required', 'boolean'],
            'receive_notification' => ['required', 'boolean'],
        ]);
        $student = Student::query()->where('uuid', $data['student_uuid'])->firstOrFail();
        $action->linkParent($request->user(), $guardian, $student, $data['relationship'],
            (bool) $data['is_primary_contact'], (bool) $data['receive_notification']);
        return to_route('portal.master-data.index', ['kind' => 'parents'])
            ->with('success', 'Orang tua berhasil ditautkan ke siswa.');
    }

    private function find(string $kind, string $ref): \Illuminate\Database\Eloquent\Model
    {
        $modelClass = match ($kind) {
            'users' => User::class, 'students' => Student::class,
            'teachers' => Teacher::class, 'parents' => Guardian::class,
            'classes' => SchoolClass::class, 'years' => AcademicYear::class,
        };
        if ($kind === 'years') {
            abort_unless(ctype_digit($ref), 404);
            return $modelClass::query()->findOrFail((int) $ref);
        }
        abort_unless((bool) preg_match('/^[0-9a-f-]{36}$/i', $ref), 404);
        return $modelClass::query()->where('uuid', $ref)->firstOrFail();
    }

    /** Allowlisted form fields only; no user_id, role IDs, balances or hidden status mutation. */
    private function rules(string $kind, ?\Illuminate\Database\Eloquent\Model $model = null): array
    {
        $create = $model === null;
        $required = $create ? 'required' : 'sometimes';
        $nullable = ['sometimes', 'nullable'];
        return match ($kind) {
            'users' => [
                'name' => [$required, 'string', 'max:150'],
                'username' => [$required, 'regex:/^[A-Za-z0-9._-]+$/', 'max:80', Rule::unique('users', 'username')->ignore($model?->id)],
                'email' => [$required, 'email', 'max:255', Rule::unique('users', 'email')->ignore($model?->id)],
                'phone' => [...$nullable, 'string', 'max:25'],
                'password' => $create ? ['required', 'string', 'min:12', 'max:255', 'confirmed'] : ['prohibited'],
                'password_confirmation' => $create ? ['required', 'string'] : ['prohibited'],
                'roles' => $create ? ['required', 'array', 'min:1', 'max:5'] : ['sometimes', 'array', 'min:1', 'max:5'],
                'roles.*' => ['required', 'string', 'distinct', Rule::exists('roles', 'name')],
                'is_active' => ['prohibited'],
            ],
            'students' => [
                'user_uuid' => $create ? ['required', 'uuid', Rule::exists('users', 'uuid')->whereNull('deleted_at')] : ['prohibited'],
                'nis' => [$required, 'string', 'max:40', Rule::unique('students', 'nis')->ignore($model?->id)],
                'nisn' => [...$nullable, 'string', 'max:40', Rule::unique('students', 'nisn')->ignore($model?->id)],
                'gender' => [$required, Rule::in(Gender::values())],
                'birth_place' => [$required, 'string', 'max:150'],
                'birth_date' => [$required, 'date_format:Y-m-d', 'before:today'],
                'address' => [$required, 'string', 'max:1000'],
                'admission_date' => [$required, 'date_format:Y-m-d'],
                'graduation_date' => [...$nullable, 'date_format:Y-m-d'],
                'status' => [$required, Rule::in(StudentStatus::values())],
            ],
            'teachers' => [
                'user_uuid' => $create ? ['required', 'uuid', Rule::exists('users', 'uuid')->whereNull('deleted_at')] : ['prohibited'],
                'nip' => [$required, 'string', 'max:60', Rule::unique('teachers', 'nip')->ignore($model?->id)],
                'employee_number' => [$required, 'string', 'max:60', Rule::unique('teachers', 'employee_number')->ignore($model?->id)],
                'gender' => [$required, Rule::in(Gender::values())],
                'birth_place' => [$required, 'string', 'max:150'],
                'birth_date' => [$required, 'date_format:Y-m-d', 'before:today'],
                'address' => [$required, 'string', 'max:1000'],
                'employment_status' => [$required, Rule::in(TeacherEmploymentStatus::values())],
                'join_date' => [$required, 'date_format:Y-m-d'],
                'status' => [$required, Rule::in(TeacherStatus::values())],
            ],
            'parents' => [
                'user_uuid' => $create ? ['required', 'uuid', Rule::exists('users', 'uuid')->whereNull('deleted_at')] : ['prohibited'],
                'occupation' => [$required, 'string', 'max:120'],
                'address' => [$required, 'string', 'max:1000'],
            ],
            'classes' => [
                'year_ref' => [$required, 'integer', Rule::exists('academic_years', 'id')],
                'teacher_uuid' => [...$nullable, 'uuid', Rule::exists('teachers', 'uuid')->whereNull('deleted_at')],
                'code' => [$required, 'string', 'max:50', Rule::unique('school_classes', 'code')->ignore($model?->id)],
                'name' => [$required, 'string', 'max:150'],
                'grade_level' => [$required, 'string', 'max:20'],
                'major' => [...$nullable, 'string', 'max:120'],
                'status' => [$required, Rule::in(SchoolClassStatus::values())],
            ],
            'years' => [
                'name' => [$required, 'string', 'max:100', Rule::unique('academic_years', 'name')->ignore($model?->id)],
                'start_date' => [$required, 'date_format:Y-m-d'],
                'end_date' => [$required, 'date_format:Y-m-d', 'after:start_date'],
                'is_active' => ['prohibited'],
            ],
        };
    }
}
