<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\PortalFinanceIndexRequest;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SppBill;
use App\Queries\Finance\PortalFinancePageQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PortalFinanceController extends Controller
{
    public function index(PortalFinanceIndexRequest $request, PortalFinancePageQuery $query): Response
    {
        $savings = Gate::allows('viewAny', SavingAccount::class);
        $spp = Gate::allows('viewAny', SppBill::class);
        abort_unless($savings || $spp, 403);
        $data = $request->validated();
        $type = $data['type'] ?? ($savings ? 'savings' : 'spp');
        abort_unless($type === 'savings' ? $savings : $spp, 403);
        return Inertia::render('Finance/Index', [
            'finance' => fn () => $query->index($request->user(), $type, $data),
        ]);
    }

    public function saving(Request $request, SavingAccount $savingAccount, PortalFinancePageQuery $query): Response
    {
        Gate::authorize('view', $savingAccount);
        return Inertia::render('Finance/SavingShow', [
            'finance' => fn () => $query->accountDetail($request->user(), $savingAccount),
        ]);
    }

    public function spp(Request $request, SppBill $sppBill, PortalFinancePageQuery $query): Response
    {
        Gate::authorize('view', $sppBill);
        return Inertia::render('Finance/SppShow', [
            'finance' => fn () => $query->billDetail($request->user(), $sppBill),
        ]);
    }
}
