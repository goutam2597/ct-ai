<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Modules\ApiPlatform\Services\ApiRateService;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Opportunities tab: the ranked next-steps list built from the same fixed
 * rules as the API endpoint (build plan §6.1 Opportunities row).
 */
final class OpportunityController extends Controller
{
    public function index(Brand $brand, ApiRateService $rates): Response
    {
        Gate::authorize('view', $brand);

        $opportunities = $rates->opportunities($brand, 30);

        return Inertia::render('Brands/Opportunities', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'opportunities' => $opportunities,
        ]);
    }
}
