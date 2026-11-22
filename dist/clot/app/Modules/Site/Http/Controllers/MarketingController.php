<?php

namespace App\Modules\Site\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public marketing pages. The pricing page reads the LIVE plan rows —
 * operators never touch code to change prices (build plan §6.3).
 */
final class MarketingController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('Site/Home', [
            'engines' => array_keys(config('clot-collection.engines')),
        ]);
    }

    public function pricing(): Response
    {
        $plans = Plan::query()->orderBy('sort_order')->get()
            ->map(fn (Plan $plan) => [
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'priceUsd' => $plan->monthlyPriceUsd(),
                'flags' => $plan->feature_flags,
                'isDefault' => $plan->is_default,
            ]);

        return Inertia::render('Site/Pricing', ['plans' => $plans]);
    }
}
