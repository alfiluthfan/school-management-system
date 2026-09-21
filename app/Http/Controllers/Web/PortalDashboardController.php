<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Queries\Dashboard\DashboardOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PortalDashboardController extends Controller
{
    public function __invoke(Request $request, DashboardOverviewQuery $query): Response
    {
        $validated = $request->validate([
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        abort_unless($user && $user->hasAnyPermission([
            'dashboard.admin', 'dashboard.principal', 'dashboard.teacher',
            'dashboard.student', 'dashboard.parent',
        ]), 403);

        $date = isset($validated['date'])
            ? CarbonImmutable::createFromFormat('!Y-m-d', $validated['date'], config('app.timezone'))
            : CarbonImmutable::now(config('app.timezone'));

        return Inertia::render('Dashboard/Index', [
            // Reuses the exact read-only query behind GET /api/v1/dashboard/overview.
            'overview' => fn (): array => $query->execute($user, $date),
        ]);
    }
}
