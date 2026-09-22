<?php
namespace App\Support\MasterData;

use App\Models\Auth\User;

final class MasterDataCatalog
{
    public const KINDS = ['users', 'students', 'teachers', 'parents', 'classes', 'years'];

    private const VIEW = [
        'users' => ['user.view.all'],
        'students' => ['student.view.all', 'student.view.class'],
        'teachers' => ['teacher.view.all'],
        'parents' => ['parent.view.all'],
        'classes' => ['class.view.all', 'class.view.assigned'],
        'years' => ['academic-year.view'],
    ];

    private const CREATE = [
        'users' => 'user.create', 'students' => 'student.create',
        'teachers' => 'teacher.create', 'parents' => 'parent.create',
        'classes' => 'class.create', 'years' => 'academic-year.create',
    ];

    private const UPDATE = [
        'users' => 'user.update', 'students' => 'student.update',
        'teachers' => 'teacher.update', 'parents' => 'parent.update',
        'classes' => 'class.update', 'years' => 'academic-year.update',
    ];

    public static function kind(string $kind): string
    {
        abort_unless(in_array($kind, self::KINDS, true), 404);
        return $kind;
    }

    public static function canView(User $actor, string $kind): bool
    {
        return $actor->hasAnyPermission(self::VIEW[self::kind($kind)]);
    }

    public static function canCreate(User $actor, string $kind): bool
    {
        $allow = $actor->hasPermission(self::CREATE[self::kind($kind)]);
        return $kind !== 'users' ? $allow : $allow && $actor->hasPermission('role.assign');
    }

    public static function canUpdate(User $actor, string $kind): bool
    {
        return $actor->hasPermission(self::UPDATE[self::kind($kind)]);
    }

    public static function tabs(User $actor): array
    {
        $labels = [
            'users' => 'Akun', 'students' => 'Siswa', 'teachers' => 'Guru',
            'parents' => 'Orang tua', 'classes' => 'Kelas', 'years' => 'Tahun ajaran',
        ];
        $result = [];
        foreach ($labels as $key => $label) {
            if (self::canView($actor, $key)) {
                $result[] = [
                    'key' => $key, 'label' => $label,
                    'can_create' => self::canCreate($actor, $key),
                    'can_update' => self::canUpdate($actor, $key),
                ];
            }
        }
        return $result;
    }
}
