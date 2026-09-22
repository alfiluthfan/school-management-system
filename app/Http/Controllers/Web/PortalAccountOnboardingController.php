<?php

namespace App\Http\Controllers\Web;

use App\Actions\MasterData\CreateSchoolAccountAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\MasterData\CreateSchoolAccountRequest;
use Illuminate\Http\RedirectResponse;

final class PortalAccountOnboardingController extends Controller
{
    public function store(CreateSchoolAccountRequest $request, CreateSchoolAccountAction $action): RedirectResponse
    {
        $data = $request->validated();
        $action->execute($request->user(), $data);
        $destination = match ($data['role']) {
            'student' => 'students', 'teacher' => 'teachers', 'parent' => 'parents',
            default => 'users',
        };
        return to_route('portal.master-data.index', ['kind' => $destination])
            ->with('success', 'Akun dan data '.($destination === 'users' ? 'pengguna' : $destination).' berhasil dibuat dalam satu proses.');
    }
}
