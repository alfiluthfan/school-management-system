<?php

namespace App\Http\Controllers\Web;

use App\Actions\Finance\RecordCashierTransactionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\CashierTransactionRequest;
use App\Queries\Finance\FinanceCashierQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

final class PortalFinanceCashierController extends Controller
{
    public function index(Request $request, FinanceCashierQuery $query): Response
    {
        FinanceCashierQuery::authorize($request->user());
        $data = Validator::make($request->all(), [
            'search' => ['nullable', 'string', 'max:100'],
            'student' => ['nullable', 'uuid'],
        ])->validate();
        return Inertia::render('Finance/Cashier', [
            'cashier' => fn () => $query->index($request->user(), $data['search'] ?? null, $data['student'] ?? null),
        ]);
    }

    public function savings(CashierTransactionRequest $request, RecordCashierTransactionAction $action): RedirectResponse
    {
        $result = $action->execute($request->user(), $request->validated(), 'savings');
        return to_route('portal.finance.cashier.index', ['student' => $request->validated('student_uuid')])
            ->with('success', $result['duplicate'] ? 'Transaksi sudah tercatat; tidak dibuat duplikat.' : 'Transaksi tabungan berhasil dicatat.');
    }

    public function spp(CashierTransactionRequest $request, RecordCashierTransactionAction $action): RedirectResponse
    {
        $result = $action->execute($request->user(), $request->validated(), 'spp');
        return to_route('portal.finance.cashier.index', ['student' => $request->validated('student_uuid')])
            ->with('success', $result['duplicate'] ? 'Pembayaran sudah tercatat; tidak dibuat duplikat.' : 'Pembayaran SPP tunai berhasil dicatat.');
    }
}
