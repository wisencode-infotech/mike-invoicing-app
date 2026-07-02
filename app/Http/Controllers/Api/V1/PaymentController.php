<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    use ApiResponses;

    public function show(Payment $payment): JsonResponse
    {
        $this->authorize('view', $payment);

        return $this->success(new PaymentResource($payment));
    }
}
