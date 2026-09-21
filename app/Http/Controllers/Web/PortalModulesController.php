<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalModulesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyPermission([
            'dashboard.admin', 'dashboard.principal', 'dashboard.teacher',
            'dashboard.student', 'dashboard.parent',
        ]), 403);

        return Inertia::render('Modules/Index');
    }
}
