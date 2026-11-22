<?php

use App\Modules\ApiPlatform\Http\Controllers\ApiBrandsController;
use App\Modules\ApiPlatform\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

// REST API v1 (build plan §6.1): bearer key auth, 120 req/min, workspace-
// scoped. Stateful web middleware deliberately NOT applied (stateless API).
Route::prefix('v1')
    ->middleware([AuthenticateApiKey::class])
    ->group(function (): void {
        Route::get('/brands', [ApiBrandsController::class, 'index']);
        Route::get('/brands/{brandId}', [ApiBrandsController::class, 'show']);
        Route::get('/brands/{brandId}/prompts', [ApiBrandsController::class, 'prompts']);
        Route::get('/brands/{brandId}/competitors', [ApiBrandsController::class, 'competitors']);
        Route::get('/brands/{brandId}/sources', [ApiBrandsController::class, 'sources']);
        Route::get('/brands/{brandId}/answers', [ApiBrandsController::class, 'answers']);
        Route::get('/brands/{brandId}/opportunities', [ApiBrandsController::class, 'opportunities']);
        Route::post('/brands/{brandId}/prompts', [ApiBrandsController::class, 'addPrompts']);
    });
