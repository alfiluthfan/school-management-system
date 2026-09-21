<?php

namespace App\Http\Middleware;

use App\Models\Auth\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'portal';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => function () use ($request): ?array {
                    $user = $request->user();
                    if (! $user instanceof User || ! $user->is_active) {
                        return null;
                    }

                    // Explicit allowlist: no user primary key, password or unrelated PII.
                    return [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'roles' => $user->roles()->pluck('name')->values()->all(),
                        'permissions' => $user->permissionNames()->all(),
                    ];
                },
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
