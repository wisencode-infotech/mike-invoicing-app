<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Concerns\ApiResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecurringInvoiceProfileApiRequest;
use App\Http\Resources\RecurringInvoiceProfileResource;
use App\Services\RecurringInvoiceService;
use Illuminate\Http\JsonResponse;

class RecurringInvoiceProfileController extends Controller
{
    use ApiResponses;

    public function __construct(protected RecurringInvoiceService $recurringInvoices) {}

    public function store(StoreRecurringInvoiceProfileApiRequest $request): JsonResponse
    {
        $sourceInvoice = $request->sourceInvoice();
        $this->authorize('makeRecurring', $sourceInvoice);

        $profile = $this->recurringInvoices->createProfile($sourceInvoice, $request->profileData());

        return $this->success(new RecurringInvoiceProfileResource($profile), 'Recurring schedule created successfully.', 201);
    }
}
