<?php

use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\Portal\PortalInvoiceController;
use App\Http\Controllers\Portal\PortalPaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductCsvImportController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [CompanySettingsController::class, 'update'])->name('settings.update');

    Route::resource('customers', CustomerController::class);

    Route::get('products/import', [ProductCsvImportController::class, 'create'])->name('products.import.create');
    Route::post('products/import', [ProductCsvImportController::class, 'store'])->name('products.import.store');
    Route::resource('products', ProductController::class)->except('show');

    Route::resource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::patch('invoices/{invoice}/notes', [InvoiceController::class, 'updateNotes'])->name('invoices.notes.update');
    Route::get('invoices/{invoice}/pdf', [InvoicePdfController::class, 'show'])->name('invoices.pdf');
    Route::get('invoices/{invoice}/receipt', [InvoicePdfController::class, 'receipt'])->name('invoices.receipt');
    Route::post('invoices/{invoice}/payment-link', [InvoiceController::class, 'createPaymentLink'])->name('invoices.payment-link.create');
});

// Public, token-secured customer portal — no auth, rate-limited to deter
// token brute-forcing (see docs/ARCHITECTURE.md section 7). Full portal
// instrumentation (access/click events, owner notifications) is Phase 9.
Route::middleware('throttle:'.(int) config('portal.rate_limit_per_minute').',1')->group(function () {
    Route::get('portal/{paymentLink:token}', [PortalInvoiceController::class, 'show'])->name('portal.show');
    Route::get('portal/{paymentLink:token}/pay', [PortalPaymentController::class, 'redirect'])->name('portal.pay');
});

require __DIR__.'/auth.php';
