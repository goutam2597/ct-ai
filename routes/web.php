<?php

use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\WebhookController;
use App\Modules\Collection\Http\Controllers\BrandController;
use App\Modules\Ops\Http\Controllers\SetupController;
use App\Modules\Tenancy\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Browser installer (Mono Market gate 2.2). Sealed = 404 on every route.
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup/database', [SetupController::class, 'database'])->name('setup.database');
Route::post('/setup/account', [SetupController::class, 'account'])->name('setup.account');
Route::post('/setup/finish', [SetupController::class, 'finish'])->name('setup.finish');

// Placeholder auth entry until the auth sprint lands; keeps the Authenticate
// middleware's redirect target valid. Replaced with real sign-in UI then.
Route::get('/login', function () {
    return Inertia::render('Welcome');
})->name('login');

// Gateway webhooks: signature-verified inside the adapter, CSRF-exempt.
Route::withoutMiddleware([VerifyCsrfToken::class])
    ->post('/webhooks/billing/{provider}', WebhookController::class)
    ->name('billing.webhook');

Route::middleware(['auth', 'workspace.context'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])
        ->name('workspaces.switch');

    Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
    Route::post('/brands/{brand}/sweep', [BrandController::class, 'runSweep'])->name('brands.sweep');

    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
});
