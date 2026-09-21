<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reporting\DashboardOverviewRequest;
use App\Queries\Dashboard\DashboardOverviewQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function overview(
        DashboardOverviewRequest $request,
        DashboardOverviewQuery $query
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->hasAnyPermission([
                'dashboard.admin',
                'dashboard.principal',
                'dashboard.teacher',
                'dashboard.student',
                'dashboard.parent',
            ]),
            403
        );

        $date = $request->filled('date')
            ? CarbonImmutable::parse(
                $request->validated('date'),
                config('app.timezone')
            )
            : CarbonImmutable::now(
                config('app.timezone')
            );

        return response()->json([
            'data' => $query->execute(
                $user,
                $date
            ),
        ]);
    }
}
