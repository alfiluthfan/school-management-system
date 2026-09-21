<?php
namespace App\Http\Controllers\Web;

use App\Actions\Approvals\SubmitApprovalRequestAction;
use App\Actions\Savings\DepositSavingAction;
use App\Actions\Savings\WithdrawSavingAction;
use App\Actions\Spp\RecordSppPaymentAction;
use App\Enums\Finance\PaymentMethod;
use App\Enums\System\ApprovalAction;
use App\Enums\System\ApprovalModule;
use App\Http\Controllers\Controller;
use App\Http\Requests\Approvals\SubmitSavingReversalApprovalRequest;
use App\Http\Requests\Approvals\SubmitSppPaymentVoidApprovalRequest;
use App\Http\Requests\Savings\DepositSavingRequest;
use App\Http\Requests\Savings\WithdrawSavingRequest;
use App\Http\Requests\Spp\StoreSppPaymentRequest;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use App\Models\System\Approval;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class PortalFinanceActionController extends Controller
{
    public function deposit(DepositSavingRequest $request, SavingAccount $savingAccount,
        DepositSavingAction $action): RedirectResponse
    {
        Gate::authorize('view', $savingAccount);
        Gate::authorize('deposit', $savingAccount);
        $action->execute(actor: $request->user(), account: $savingAccount,
            amount: (string) $request->validated('amount'), description: $request->validated('description'));
        return to_route('portal.finance.savings.show', $savingAccount)
            ->with('success', 'Setoran tabungan berhasil dicatat.');
    }

    public function withdraw(WithdrawSavingRequest $request, SavingAccount $savingAccount,
        WithdrawSavingAction $action): RedirectResponse
    {
        Gate::authorize('view', $savingAccount);
        Gate::authorize('withdraw', $savingAccount);
        $action->execute(actor: $request->user(), account: $savingAccount,
            amount: (string) $request->validated('amount'), description: $request->validated('description'));
        return to_route('portal.finance.savings.show', $savingAccount)
            ->with('success', 'Penarikan tabungan berhasil dicatat.');
    }

    public function pay(StoreSppPaymentRequest $request, SppBill $sppBill,
        RecordSppPaymentAction $action): RedirectResponse
    {
        Gate::authorize('view', $sppBill);
        Gate::authorize('create', SppPayment::class);
        $action->execute(actor: $request->user(), bill: $sppBill,
            amount: (string) $request->validated('amount'),
            paymentMethod: PaymentMethod::from($request->validated('payment_method')),
            referenceNumber: $request->validated('reference_number'),
            notes: $request->validated('notes'));
        return to_route('portal.finance.spp.show', $sppBill)
            ->with('success', 'Pembayaran SPP berhasil dicatat.');
    }

    public function requestReversal(SubmitSavingReversalApprovalRequest $request,
        SavingAccount $savingAccount, SavingTransaction $savingTransaction,
        SubmitApprovalRequestAction $action): RedirectResponse
    {
        Gate::authorize('view', $savingAccount);
        abort_unless($savingTransaction->saving_account_id === $savingAccount->id, 404);
        Gate::authorize('view', $savingTransaction);
        Gate::authorize('void', $savingTransaction);
        Gate::authorize('create', Approval::class);
        $action->execute(requester: $request->user(), module: ApprovalModule::Saving,
            action: ApprovalAction::Void, entity: $savingTransaction,
            reason: $request->validated('reason'), payload: []);
        return to_route('portal.finance.savings.show', $savingAccount)
            ->with('success', 'Permintaan reversal diajukan untuk persetujuan. Saldo belum berubah.');
    }

    public function requestPaymentVoid(SubmitSppPaymentVoidApprovalRequest $request,
        SppBill $sppBill, SppPayment $sppPayment,
        SubmitApprovalRequestAction $action): RedirectResponse
    {
        Gate::authorize('view', $sppBill);
        abort_unless($sppPayment->spp_bill_id === $sppBill->id, 404);
        Gate::authorize('view', $sppPayment);
        Gate::authorize('void', $sppPayment);
        Gate::authorize('create', Approval::class);
        $action->execute(requester: $request->user(), module: ApprovalModule::Spp,
            action: ApprovalAction::Void, entity: $sppPayment,
            reason: $request->validated('reason'), payload: []);
        return to_route('portal.finance.spp.show', $sppBill)
            ->with('success', 'Permintaan pembatalan pembayaran diajukan. Tagihan belum berubah.');
    }
}
