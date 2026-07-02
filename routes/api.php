<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\RecurringInvoiceProfileController;
use App\Http\Middleware\EnsureApiTokenIsValid;
use Illuminate\Support\Facades\Route;

// See docs/ARCHITECTURE.md section 9 and README.md "External API" for the
// full endpoint reference, auth, and response envelope documentation.
Route::prefix('v1')->middleware(EnsureApiTokenIsValid::class)->group(function () {
    Route::post('customers', [CustomerController::class, 'store']);
    Route::get('customers', [CustomerController::class, 'index']);
    Route::get('customers/{customer}', [CustomerController::class, 'show']);
    Route::patch('customers/{customer}', [CustomerController::class, 'update']);

    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('invoices/{invoice}/items', [InvoiceController::class, 'addItem']);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send']);
    Route::get('invoices/{invoice}/status', [InvoiceController::class, 'status']);

    Route::post('recurring-invoices', [RecurringInvoiceProfileController::class, 'store']);

    Route::get('payments/{payment}', [PaymentController::class, 'show']);
});
