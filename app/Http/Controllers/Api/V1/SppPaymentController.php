<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Spp\RecordSppPaymentAction;
use App\Authorization\SchoolDataScope;
use App\Enums\Finance\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\IndexRequest;
use App\Http\Requests\Spp\StoreSppPaymentRequest;
use App\Http\Resources\Spp\SppPaymentResource;
use App\Models\Finance\SppBill;
use App\Models\Finance\SppPayment;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SppPaymentController extends Controller
{
    public function index(
        IndexRequest $request,
        SppBill $sppBill
    ): AnonymousResourceCollection {
        Gate::authorize('view', $sppBill);
        Gate::authorize('viewAny', SppPayment::class);

        $payments = SchoolDataScope::sppPayments($request->user())
            ->where('spp_bill_id', $sppBill->id)
            ->with(['bill', 'creator'])
            ->latest('payment_date')
            ->paginate($request->perPage())
            ->withQueryString();

        return SppPaymentResource::collection($payments);
    }

    public function show(
        SppPayment $sppPayment
    ): SppPaymentResource {
        Gate::authorize('view', $sppPayment);

        $sppPayment->load(['bill', 'creator']);

        return SppPaymentResource::make($sppPayment);
    }

    public function store(
        StoreSppPaymentRequest $request,
        SppBill $sppBill,
        RecordSppPaymentAction $action
    ) {
        Gate::authorize('create', SppPayment::class);

        $payment = $action->execute(
            actor: $request->user(),
            bill: $sppBill,
            amount: (string) $request->validated('amount'),
            paymentMethod: PaymentMethod::from(
                $request->validated('payment_method')
            ),
            referenceNumber: $request->validated('reference_number'),
            notes: $request->validated('notes'),
        );

        $payment->load(['bill', 'creator']);

        return SppPaymentResource::make($payment)
            ->response()
            ->setStatusCode(201);
    }
}
