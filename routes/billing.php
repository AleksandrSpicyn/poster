<?php

declare(strict_types=1);

use App\Http\Controllers\Billing\CheckoutController as BillingCheckoutController;
use App\Http\Controllers\Billing\DetachCardController as BillingDetachCardController;
use App\Http\Controllers\Billing\HistoryController as BillingHistoryController;
use App\Http\Controllers\Billing\NextPlanController as BillingNextPlanController;
use App\Http\Controllers\Billing\OrderController as BillingOrderController;
use App\Http\Controllers\Billing\QuoteController as BillingQuoteController;
use App\Http\Controllers\Billing\RenewalConsentController;
use App\Http\Controllers\Billing\RunUsageController as BillingRunUsageController;
use App\Http\Controllers\Billing\ShotUsageController as BillingShotUsageController;
use App\Http\Controllers\Billing\ShowController as BillingShowController;
use App\Http\Controllers\Billing\StoreController as BillingStoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('billing')->name('billing.')->group(function (): void {
    Route::post('quotes', BillingQuoteController::class)->middleware('can:manageSeries')->name('quotes.store');
    Route::get('history', BillingHistoryController::class)->name('history');
    Route::prefix('subscription')->name('subscription.')->group(function (): void {
        Route::patch('renewal', RenewalConsentController::class)->name('renewal');
        Route::patch('next-plan', BillingNextPlanController::class)->name('next_plan');
    });
    Route::delete('payment-method', BillingDetachCardController::class)->name('payment_method.destroy');
    Route::prefix('usage')->name('usage.')->group(function (): void {
        Route::get('shots/{shot}', BillingShotUsageController::class)->whereNumber('shot')->middleware('can:manageSeries')->name('shot');
        Route::get('runs/{production_run}', BillingRunUsageController::class)->whereNumber('production_run')->name('run');
    });
    Route::get('checkout/{product}', BillingCheckoutController::class)->name('checkout');
    Route::prefix('orders')->name('orders.')->group(function (): void {
        Route::post('{product}', BillingStoreController::class)->middleware('throttle:12,1')->name('store');
        Route::get('{order}', BillingOrderController::class)->whereNumber('order')->name('show');
    });
    Route::get('{section?}', BillingShowController::class)->name('show');
});
