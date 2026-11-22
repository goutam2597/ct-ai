<?php

use App\Modules\Collection\Http\Controllers\BrandController;
use App\Modules\Tenancy\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::middleware(['auth', 'workspace.context'])->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::post('/workspaces/{workspace}/switch', [WorkspaceController::class, 'switch'])
        ->name('workspaces.switch');

    Route::get('/brands/{brand}', [BrandController::class, 'show'])->name('brands.show');
    Route::post('/brands/{brand}/sweep', [BrandController::class, 'runSweep'])->name('brands.sweep');
});
