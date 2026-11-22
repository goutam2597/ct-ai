<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Modules\Analytics\Http\Controllers\AlertsController;
use App\Modules\ApiPlatform\Http\Controllers\ApiKeysController;
use App\Modules\Billing\Http\Controllers\BillingController;
use App\Modules\Billing\Http\Controllers\WebhookController;
use App\Modules\Collection\Http\Controllers\BrandController;
use App\Modules\Collection\Http\Controllers\ReportController;
use App\Modules\Ops\Http\Controllers\Admin\BillingKeysController;
use App\Modules\Ops\Http\Controllers\Admin\DashboardController;
use App\Modules\Ops\Http\Controllers\Admin\EnginesController;
use App\Modules\Ops\Http\Controllers\Admin\WorkspacesController;
use App\Modules\Ops\Http\Controllers\SetupController;
use App\Modules\Site\Http\Controllers\FreeCheckController;
use App\Modules\Site\Http\Controllers\MarketingController;
use App\Modules\Site\Http\Controllers\SharedReportController;
use App\Modules\Tenancy\Http\Controllers\WorkspaceController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;

// Public marketing site (Site module). The landing "home" route is defined
// here; the pricing page reads the live plan rows.
Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('/pricing', [MarketingController::class, 'pricing'])->name('site.pricing');
Route::get('/free-ai-visibility-check', [FreeCheckController::class, 'create'])->name('site.free-check');
Route::post('/free-ai-visibility-check', [FreeCheckController::class, 'store'])->name('site.free-check.run');

// Auth (rate-limited inside the controllers).
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('register.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])
        ->name('password.request');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])
        ->name('password.reset');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Password reset + email verification (framework notification contracts).
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
    ->middleware('guest')
    ->name('password.email');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.update');

Route::get('/verify-email', [EmailVerificationController::class, 'prompt'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.notice');
Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'confirm'])
    ->middleware(['auth', 'signed', 'throttle:6,1'])
    ->name('verification.verify');
Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');

// Browser installer (Mono Market gate 2.2). Sealed = 404 on every route.
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup/database', [SetupController::class, 'database'])->name('setup.database');
Route::post('/setup/account', [SetupController::class, 'account'])->name('setup.account');
Route::post('/setup/finish', [SetupController::class, 'finish'])->name('setup.finish');

// Gateway webhooks: signature-verified inside the adapter, CSRF-exempt.
Route::withoutMiddleware([VerifyCsrfToken::class])
    ->post('/webhooks/billing/{provider}', WebhookController::class)
    ->name('billing.webhook');

Route::middleware(['auth', 'verified', 'workspace.context'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])
        ->name('workspaces.switch');

    Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
    Route::get('/brands/{brand}/answers', [BrandController::class, 'answers'])->name('brands.answers');
    Route::get('/brands/{brand}/report', [ReportController::class, 'pdf'])
        ->name('brands.report');
    Route::get('/brands/{brand}/share-links', [ReportController::class, 'shareLinks'])
        ->name('brands.share-links');
    Route::post('/brands/{brand}/share-links', [ReportController::class, 'createShareLink'])
        ->name('brands.share-links.create');
    Route::delete('/brands/{brand}/share-links/{shareLink}', [ReportController::class, 'revokeShareLink'])
        ->name('brands.share-links.destroy');
    Route::post('/brands/{brand}/sweep', [BrandController::class, 'runSweep'])->name('brands.sweep');

    Route::get('/alerts', [AlertsController::class, 'index'])->name('alerts.index');
    Route::post('/alerts/channels', [AlertsController::class, 'addChannel'])->name('alerts.channels.store');
    Route::delete('/alerts/channels/{channel}', [AlertsController::class, 'removeChannel'])->name('alerts.channels.destroy');

    Route::get('/api-keys', [ApiKeysController::class, 'index'])->name('api-keys.index');
    Route::post('/api-keys', [ApiKeysController::class, 'store'])->name('api-keys.store');
    Route::delete('/api-keys/{apiKey}', [ApiKeysController::class, 'destroy'])->name('api-keys.destroy');

    Route::get('/billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/billing/checkout/{plan}', [BillingController::class, 'checkout'])->name('billing.checkout');
});

// Operator panel (separate from every workspace; gate defined in OpsServiceProvider).
Route::middleware(['auth', 'can:access-admin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/workspaces', [WorkspacesController::class, 'index'])->name('workspaces');
    Route::get('/engines', [EnginesController::class, 'index'])->name('engines');
    Route::post('/engines/{engine}/test', [EnginesController::class, 'test'])->name('engines.test');
    Route::get('/billing', [BillingKeysController::class, 'index'])->name('billing');
    Route::post('/billing/keys', [BillingKeysController::class, 'save'])->name('billing.keys');
});

// Public share links (no auth; token is the credential).
Route::get('/reports/{token}', [SharedReportController::class, 'show'])
    ->name('reports.shared');
