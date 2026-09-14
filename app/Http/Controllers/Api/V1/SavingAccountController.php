<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\SchoolDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\IndexRequest;
use App\Http\Resources\Savings\SavingAccountResource;
use App\Models\Finance\SavingAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SavingAccountController extends Controller
{
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SavingAccount::class);

        $accounts = SchoolDataScope::savingAccounts($request->user())
            ->with('student.user')
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return SavingAccountResource::collection($accounts);
    }

    public function show(
        SavingAccount $savingAccount
    ): SavingAccountResource {
        Gate::authorize('view', $savingAccount);

        $savingAccount->load('student.user');

        return SavingAccountResource::make($savingAccount);
    }
}
