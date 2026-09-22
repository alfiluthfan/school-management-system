<?php
namespace App\Actions\MasterData;

use App\Enums\Academic\EnrollmentStatus;
use App\Enums\Academic\SchoolClassStatus;
use App\Enums\Academic\StudentStatus;
use App\Models\Academic\AcademicYear;
use App\Models\Academic\Guardian;
use App\Models\Academic\SchoolClass;
use App\Models\Academic\Student;
use App\Models\Academic\StudentClassEnrollment;
use App\Models\Academic\Teacher;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use App\Services\System\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageMasterDataAction
{
    public function __construct(private AuditLogger $audit) {}

    public function persist(User $actor, string $kind, array $data, ?Model $existing = null): Model
    {
        return DB::transaction(function () use ($actor, $kind, $data, $existing): Model {
            $isNew = $existing === null;
            $attributes = $data;
            $user = null;
            if ($kind === 'users') {
                $roles = $attributes['roles'] ?? null;
                unset($attributes['roles'], $attributes['password_confirmation']);
                if (!$isNew) {
                    // Do not inadvertently reset a password when editing profile fields.
                    unset($attributes['password']);
                }
                if ($isNew) {
                    $user = User::query()->create([...$attributes, 'is_active' => true]);
                } else {
                    $user = User::query()->whereKey($existing->getKey())->lockForUpdate()->firstOrFail();
                    $user->update($attributes);
                }
                if ($roles !== null) {
                    if (count($roles) !== 1 || count(array_unique($roles)) !== 1) {
                        throw ValidationException::withMessages(['roles' => 'Pilih tepat satu role untuk setiap akun.']);
                    }
                    $currentRoles = $user->roles()->pluck('name')->sort()->values()->all();
                    $requestedRoles = collect($roles)->sort()->values()->all();
                    if (!$isNew && $actor->is($user) && $currentRoles !== $requestedRoles) {
                        throw ValidationException::withMessages([
                            'roles' => 'Role akun sendiri tidak dapat diubah.',
                        ]);
                    }
                    $roleModels = Role::query()->whereIn('name', $roles)->get();
                    if ($roleModels->count() !== count(array_unique($roles))) {
                        throw ValidationException::withMessages(['roles' => 'Role tidak ditemukan.']);
                    }
                    // Do not sever an existing profile from its required role.
                    foreach (['student' => 'student', 'teacher' => 'teacher', 'guardian' => 'parent'] as $relation => $roleName) {
                        if ($user->{$relation}()->exists() && !in_array($roleName, $roles, true)) {
                            throw ValidationException::withMessages([
                                'roles' => "Akun memiliki profil yang membutuhkan role {$roleName}.",
                            ]);
                        }
                    }
                    if (!$isNew && $user->roles()->where('name', 'admin')->exists() && !in_array('admin', $roles, true)) {
                        $this->assertAnotherAdmin($user);
                    }
                    $user->roles()->sync($roleModels->modelKeys());
                    if (! $isNew && $currentRoles !== $requestedRoles) {
                        // Role changes must not leave privileged sessions alive.
                        $this->revokeWebSessions($user);
                    }
                }
                $this->audit->log($actor, 'master-user', $isNew ? 'CREATE' : 'UPDATE', $user,
                    null, ['uuid' => $user->uuid, 'changed_fields' => array_keys($attributes),
                        'roles' => $roles ?? $user->roles()->pluck('name')->all()]);
                return $user;
            }
            if (in_array($kind, ['students', 'teachers', 'parents'], true)) {
                $class = match ($kind) {
                    'students' => Student::class, 'teachers' => Teacher::class, 'parents' => Guardian::class,
                };
                if ($isNew) {
                    $userUuid = $attributes['user_uuid'];
                    unset($attributes['user_uuid']);
                    $user = User::query()->where('uuid', $userUuid)->lockForUpdate()->firstOrFail();
                    $relation = match ($kind) {
                        'students' => 'student', 'teachers' => 'teacher', 'parents' => 'guardian',
                    };
                    $role = match ($kind) { 'students' => 'student', 'teachers' => 'teacher', 'parents' => 'parent' };
                    if (!$user->is_active || $user->roles()->count() !== 1 || !$user->hasRole($role) || $user->{$relation}()->exists()) {
                        throw ValidationException::withMessages(['user_uuid' => 'Akun harus aktif, memiliki tepat satu role yang sesuai, dan belum memiliki profil.']);
                    }
                    $attributes['user_id'] = $user->id;
                } else {
                    unset($attributes['user_uuid']);
                    if ($kind === 'students' && ($attributes['status'] ?? 'ACTIVE') !== 'ACTIVE'
                        && $existing->enrollments()->where('status', 'ACTIVE')->exists()) {
                        throw ValidationException::withMessages(['status' => 'Selesaikan enrollment aktif sebelum menonaktifkan siswa.']);
                    }
                    if ($kind === 'teachers' && ($attributes['status'] ?? 'ACTIVE') !== 'ACTIVE'
                        && $existing->homeroomClasses()->where('status', 'ACTIVE')->exists()) {
                        throw ValidationException::withMessages(['status' => 'Alihkan wali kelas sebelum menonaktifkan guru.']);
                    }
                }
                $model = $isNew ? $class::query()->create($attributes) : $this->update($existing, $attributes);
            } elseif ($kind === 'classes') {
                if (array_key_exists('year_ref', $attributes)) {
                    $attributes['academic_year_id'] = $attributes['year_ref'];
                    unset($attributes['year_ref']);
                }
                if (array_key_exists('teacher_uuid', $attributes)) {
                    $uuid = $attributes['teacher_uuid'];
                    $teacher = $uuid ? Teacher::query()->where('uuid', $uuid)->where('status', 'ACTIVE')->first() : null;
                    if ($uuid && !$teacher) {
                        throw ValidationException::withMessages(['teacher_uuid' => 'Guru aktif tidak ditemukan.']);
                    }
                    $attributes['homeroom_teacher_id'] = $teacher?->id;
                    unset($attributes['teacher_uuid']);
                }
                if (!$isNew && array_key_exists('status', $attributes)
                    && $attributes['status'] !== 'ACTIVE'
                    && $existing->enrollments()->where('status', 'ACTIVE')->exists()) {
                    throw ValidationException::withMessages(['status' => 'Kelas dengan enrollment aktif tidak dapat dinonaktifkan atau diarsipkan.']);
                }
                if (!$isNew && array_key_exists('academic_year_id', $attributes)
                    && (int) $existing->academic_year_id !== (int) $attributes['academic_year_id']
                    && $existing->enrollments()->exists()) {
                    throw ValidationException::withMessages(['year_ref' => 'Tahun ajaran kelas berisi enrollment tidak boleh dipindah.']);
                }
                $model = $isNew ? SchoolClass::query()->create($attributes) : $this->update($existing, $attributes);
            } else {
                // Activation is a separate operation, never mass-assign is_active here.
                if (!$isNew && ($existing->classes()->exists() || $existing->sppBills()->exists())
                    && (array_key_exists('start_date', $attributes) || array_key_exists('end_date', $attributes))) {
                    if (($attributes['start_date'] ?? $existing->start_date->toDateString()) !== $existing->start_date->toDateString()
                        || ($attributes['end_date'] ?? $existing->end_date->toDateString()) !== $existing->end_date->toDateString()) {
                        throw ValidationException::withMessages(['start_date' => 'Periode tahun ajaran yang sudah digunakan tidak dapat diubah.']);
                    }
                }
                $start = $attributes['start_date'] ?? $existing?->start_date?->toDateString();
                $end = $attributes['end_date'] ?? $existing?->end_date?->toDateString();
                if ($start >= $end) {
                    throw ValidationException::withMessages(['end_date' => 'Tanggal akhir harus sesudah tanggal mulai.']);
                }
                $model = $isNew ? AcademicYear::query()->create([...$attributes, 'is_active' => false])
                    : $this->update($existing, $attributes);
            }
            $this->audit->log($actor, 'master-'.$kind, $isNew ? 'CREATE' : 'UPDATE', $model,
                null, ['changed_fields' => array_keys($attributes)]);
            return $model;
        }, 3);
    }

    private function update(Model $model, array $attributes): Model
    {
        $fresh = $model->newQuery()->whereKey($model->getKey())->lockForUpdate()->firstOrFail();
        $fresh->update($attributes);
        return $fresh;
    }

    public function setUserActive(User $actor, User $target, bool $active): void
    {
        DB::transaction(function () use ($actor, $target, $active): void {
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (!$active && $locked->is($actor)) {
                throw ValidationException::withMessages(['state' => 'Akun sendiri tidak boleh dinonaktifkan.']);
            }
            if (!$active && $locked->roles()->where('name', 'admin')->exists()) {
                $this->assertAnotherAdmin($locked);
            }
            $old = $locked->is_active;
            $locked->update(['is_active' => $active]);
            if (! $active && $old) {
                // A later reactivation must not resurrect an old session.
                $this->revokeWebSessions($locked);
            }
            $this->audit->log($actor, 'master-user', $active ? 'ACTIVATE' : 'DEACTIVATE', $locked,
                ['is_active' => $old], ['is_active' => $active]);
        }, 3);
    }

    private function assertAnotherAdmin(User $target): void
    {
        // Lock the shared role row so concurrent admin changes cannot remove both final admins.
        $adminRole = Role::query()->where('name', 'admin')->lockForUpdate()->firstOrFail();
        $others = User::query()->where('is_active', true)->where('id', '!=', $target->id)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $adminRole->id))->exists();
        if (!$others) {
            throw ValidationException::withMessages(['roles' => 'Administrator aktif terakhir tidak boleh dihapus aksesnya.']);
        }
    }

    public function resetPassword(User $actor, User $target, string $password): void
    {
        DB::transaction(function () use ($actor, $target, $password): void {
            $locked = User::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($locked->is($actor)) {
                throw ValidationException::withMessages(['password' => 'Gunakan fitur ubah kata sandi sendiri.']);
            }
            $locked->forceFill(['password' => $password])->save(); // User's hashed cast.
            $this->revokeWebSessions($locked);
            $this->audit->log($actor, 'master-user', 'RESET_PASSWORD', $locked,
                null, ['password_reset' => true]); // NEVER log passwords.
        }, 3);
    }

    /** Invalidate all other web sessions and all persistent remember-me cookies. */
    private function revokeWebSessions(User $user): void
    {
        $user->forceFill([
            'portal_session_version' => (int) $user->portal_session_version + 1,
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function activateYear(User $actor, AcademicYear $year): void
    {
        DB::transaction(function () use ($actor, $year): void {
            $years = AcademicYear::query()->orderBy('id')->lockForUpdate()->get();
            foreach ($years as $item) {
                if ($item->id !== $year->id && $item->is_active) {
                    $item->update(['is_active' => false]);
                }
            }
            $target = $years->firstWhere('id', $year->id);
            abort_unless($target, 404);
            $target->update(['is_active' => true]);
            $this->audit->log($actor, 'master-year', 'ACTIVATE', $target,
                null, ['name' => $target->name]);
        }, 3);
    }

    public function enroll(User $actor, Student $student, SchoolClass $class, string $joinedAt): void
    {
        DB::transaction(function () use ($actor, $student, $class, $joinedAt): void {
            $freshStudent = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $freshClass = SchoolClass::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $year = $freshClass->academicYear;
            // Compare persisted values, regardless of whether model attributes use enum casts.
            if ($freshClass->getRawOriginal('status') !== SchoolClassStatus::Active->value
                || $freshStudent->getRawOriginal('status') !== StudentStatus::Active->value) {
                throw ValidationException::withMessages(['class_uuid' => 'Kelas atau siswa tidak aktif.']);
            }
            if ($joinedAt < $year->start_date->toDateString() || $joinedAt > $year->end_date->toDateString()) {
                throw ValidationException::withMessages(['joined_at' => 'Tanggal masuk harus dalam periode tahun ajaran.']);
            }
            $existing = StudentClassEnrollment::query()->where('student_id', $student->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->whereHas('schoolClass', fn ($q) => $q->where('academic_year_id', $year->id))
                ->lockForUpdate()->exists();
            if ($existing) {
                throw ValidationException::withMessages(['class_uuid' => 'Siswa masih memiliki enrollment aktif pada tahun ajaran ini. Selesaikan/mutasi enrollment lama dahulu.']);
            }
            $enrollment = StudentClassEnrollment::query()->create([
                'student_id' => $student->id, 'class_id' => $class->id,
                'joined_at' => $joinedAt, 'left_at' => null, 'status' => EnrollmentStatus::Active,
            ]);
            $this->audit->log($actor, 'master-enrollment', 'CREATE', $enrollment,
                null, ['student_uuid' => $student->uuid, 'class_uuid' => $class->uuid]);
        }, 3);
    }

    public function linkParent(User $actor, Guardian $parent, Student $student,
        string $relationship, bool $primary, bool $notify): void
    {
        DB::transaction(function () use ($actor, $parent, $student, $relationship, $primary, $notify): void {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            if ($parent->students()->where('students.id', $student->id)->exists()) {
                throw ValidationException::withMessages(['student_uuid' => 'Relasi orang tua dengan siswa sudah ada.']);
            }
            if ($primary) {
                DB::table('parent_students')->where('student_id', $student->id)
                    ->update(['is_primary_contact' => false]);
            }
            $parent->students()->attach($student->id, [
                'relationship' => $relationship, 'is_primary_contact' => $primary,
                'receive_notification' => $notify, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->log($actor, 'master-parent-link', 'CREATE', $parent,
                null, ['parent_uuid' => $parent->uuid, 'student_uuid' => $student->uuid,
                    'relationship' => $relationship, 'primary' => $primary]);
        }, 3);
    }
}
