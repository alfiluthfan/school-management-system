<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Savings\DepositSavingAction;
use App\Actions\Savings\ReverseSavingTransactionAction;
use App\Actions\Savings\WithdrawSavingAction;
use App\Authorization\SchoolDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\IndexRequest;
use App\Http\Requests\Savings\DepositSavingRequest;
use App\Http\Requests\Savings\ReverseSavingTransactionRequest;
use App\Http\Requests\Savings\WithdrawSavingRequest;
use App\Http\Resources\Savings\SavingTransactionResource;
use App\Models\Finance\SavingAccount;
use App\Models\Finance\SavingTransaction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SavingTransactionController extends Controller
{
    public function index(
        IndexRequest $request,
        SavingAccount $savingAccount
    ): AnonymousResourceCollection {
        Gate::authorize('view', $savingAccount);
        Gate::authorize('viewAny', SavingTransaction::class);

        $transactions = SchoolDataScope::savingTransactions($request->user())
            ->where('saving_account_id', $savingAccount->id)
            ->with(['creator', 'referenceTransaction'])
            ->latest('transaction_date')
            ->paginate($request->perPage())
            ->withQueryString();

        return SavingTransactionResource::collection($transactions);
    }

    public function show(
        SavingTransaction $savingTransaction
    ): SavingTransactionResource {
        Gate::authorize('view', $savingTransaction);

        $savingTransaction->load(['creator', 'referenceTransaction']);

        return SavingTransactionResource::make($savingTransaction);
    }

    public function deposit(
        DepositSavingRequest $request,
        SavingAccount $savingAccount,
        DepositSavingAction $action
    ) {
        Gate::authorize('deposit', $savingAccount);

        $transaction = $action->execute(
            actor: $request->user(),
            account: $savingAccount,
            amount: (string) $request->validated('amount'),
            description: $request->validated('description'),
        );

        $transaction->load('creator');

        return SavingTransactionResource::make($transaction)
            ->response()
            ->setStatusCode(201);
    }

    public function withdraw(
        WithdrawSavingRequest $request,
        SavingAccount $savingAccount,
        WithdrawSavingAction $action
    ) {
        Gate::authorize('withdraw', $savingAccount);

        $transaction = $action->execute(
            actor: $request->user(),
            account: $savingAccount,
            amount: (string) $request->validated('amount'),
            description: $request->validated('description'),
        );

        $transaction->load('creator');

        return SavingTransactionResource::make($transaction)
            ->response()
            ->setStatusCode(201);
    }

    public function reverse(
        ReverseSavingTransactionRequest $request,
        SavingTransaction $savingTransaction,
        ReverseSavingTransactionAction $action
    ) {
        Gate::authorize('void', $savingTransaction);

        $reversal = $action->execute(
            actor: $request->user(),
            original: $savingTransaction,
            reason: $request->validated('reason'),
        );

        $reversal->load(['creator', 'referenceTransaction']);

        return SavingTransactionResource::make($reversal)
            ->response()
            ->setStatusCode(201);
    }
}
