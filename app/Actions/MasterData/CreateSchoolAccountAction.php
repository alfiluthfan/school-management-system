<?php

namespace App\Actions\MasterData;

use App\Models\Auth\User;
use Illuminate\Support\Facades\DB;

/** Reuse existing account/profile Actions and their audit logs inside one atomic transaction. */
final class CreateSchoolAccountAction
{
    public function __construct(private ManageMasterDataAction $master) {}

    public function execute(User $actor, array $input): User
    {
        return DB::transaction(function () use ($actor, $input): User {
            $role = $input['role'];
            $account = $this->master->persist($actor, 'users', [
                'name' => $input['name'],
                'username' => $input['username'],
                'email' => $input['email'],
                'phone' => $input['phone'] ?? null,
                'password' => $input['password'],
                'roles' => [$role],
            ]);
            $kind = match ($role) {
                'student' => 'students', 'teacher' => 'teachers', 'parent' => 'parents',
                default => null,
            };
            if ($kind !== null) {
                $this->master->persist($actor, $kind, [
                    ...$input['profile'], 'user_uuid' => $account->uuid,
                ]);
            }
            return $account;
        }, 3);
    }
}
