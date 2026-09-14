<?php

namespace App\Http\Controllers\Api\V1;

use App\Authorization\SchoolDataScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Common\IndexRequest;
use App\Http\Resources\Spp\SppBillResource;
use App\Models\Finance\SppBill;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class SppBillController extends Controller
{
    public function index(IndexRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SppBill::class);

        $bills = SchoolDataScope::sppBills($request->user())
            ->with(['student.user', 'academicYear'])
            ->latest('billing_year')
            ->latest('billing_month')
            ->paginate($request->perPage())
            ->withQueryString();

        return SppBillResource::collection($bills);
    }

    public function show(SppBill $sppBill): SppBillResource
    {
        Gate::authorize('view', $sppBill);

        $sppBill->load([
            'student.user',
            'academicYear',
            'payments.creator',
        ]);

        return SppBillResource::make($sppBill);
    }
}
