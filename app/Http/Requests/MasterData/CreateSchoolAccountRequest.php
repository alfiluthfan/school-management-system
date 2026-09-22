<?php

namespace App\Http\Requests\MasterData;

use App\Enums\Academic\StudentStatus;
use App\Enums\Academic\TeacherEmploymentStatus;
use App\Enums\Academic\TeacherStatus;
use App\Enums\Common\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** One role, one account and exactly one matching profile (if its role needs one). */
final class CreateSchoolAccountRequest extends FormRequest
{
    private const PROFILE_FIELDS = [
        'student' => ['nis','nisn','gender','birth_place','birth_date','address','admission_date','graduation_date','status'],
        'teacher' => ['nip','employee_number','gender','birth_place','birth_date','address','employment_status','join_date','status'],
        'parent' => ['occupation','address'],
    ];

    public function authorize(): bool
    {
        $actor = $this->user();
        if (! $actor || ! $actor->hasPermission('user.create') || ! $actor->hasPermission('role.assign')) {
            return false;
        }
        return match ($this->input('role')) {
            'student' => $actor->hasPermission('student.create'),
            'teacher' => $actor->hasPermission('teacher.create'),
            'parent' => $actor->hasPermission('parent.create'),
            'admin', 'principal' => true,
            default => true, // invalid role receives validation error, not permission disclosure
        };
    }

    public function rules(): array
    {
        $role = $this->input('role');
        $role = is_string($role) ? $role : null;
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'regex:/^[A-Za-z0-9._-]+$/', 'max:80', Rule::unique('users', 'username')],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:25'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', 'string', Rule::in(['student','teacher','parent','admin','principal']), Rule::exists('roles', 'name')],
            'roles' => ['prohibited'], // never accept arrays or client-injected additional roles
            'user_id' => ['prohibited'], 'user_uuid' => ['prohibited'], 'is_active' => ['prohibited'],
        ];
        if (! isset(self::PROFILE_FIELDS[$role])) {
            $rules['profile'] = ['prohibited']; // principal/admin only need an account; no fake teacher/student profile
            return $rules;
        }

        $rules['profile'] = ['required', 'array:'.implode(',', self::PROFILE_FIELDS[$role])];
        $required = ['required'];
        $optional = ['sometimes', 'nullable'];
        if ($role === 'student') {
            $rules += [
                'profile.nis' => [...$required, 'string', 'max:40', Rule::unique('students', 'nis')],
                'profile.nisn' => [...$optional, 'string', 'max:40', Rule::unique('students', 'nisn')],
                'profile.gender' => [...$required, Rule::in(Gender::values())],
                'profile.birth_place' => [...$required, 'string', 'max:150'],
                'profile.birth_date' => [...$required, 'date_format:Y-m-d', 'before:today'],
                'profile.address' => [...$required, 'string', 'max:1000'],
                'profile.admission_date' => [...$required, 'date_format:Y-m-d'],
                'profile.graduation_date' => [...$optional, 'date_format:Y-m-d'],
                'profile.status' => [...$required, Rule::in(StudentStatus::values())],
            ];
        } elseif ($role === 'teacher') {
            $rules += [
                'profile.nip' => [...$required, 'string', 'max:60', Rule::unique('teachers', 'nip')],
                'profile.employee_number' => [...$required, 'string', 'max:60', Rule::unique('teachers', 'employee_number')],
                'profile.gender' => [...$required, Rule::in(Gender::values())],
                'profile.birth_place' => [...$required, 'string', 'max:150'],
                'profile.birth_date' => [...$required, 'date_format:Y-m-d', 'before:today'],
                'profile.address' => [...$required, 'string', 'max:1000'],
                'profile.employment_status' => [...$required, Rule::in(TeacherEmploymentStatus::values())],
                'profile.join_date' => [...$required, 'date_format:Y-m-d'],
                'profile.status' => [...$required, Rule::in(TeacherStatus::values())],
            ];
        } else {
            $rules += [
                'profile.occupation' => [...$required, 'string', 'max:120'],
                'profile.address' => [...$required, 'string', 'max:1000'],
            ];
        }
        return $rules;
    }
}
