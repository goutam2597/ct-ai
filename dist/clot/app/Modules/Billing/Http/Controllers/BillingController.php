<?php

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Tenancy\Http\Controllers\Controller;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function show(PlanFeatures $features): Response
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('view', $workspace);

        $plans = Plan::query()->withoutGlobalScopes()->orderBy('sort_order')->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'slug' => $plan->slug,
                'name' => $plan->name,
                'description' => $plan->description,
                'priceUsd' => $plan->monthlyPriceUsd(),
                'flags' => $plan->feature_flags,
                'isCurrent' => $features->plan($workspace)?->id === $plan->id,
            ]);

        return Inertia::render('Billing/Show', [
            'plans' => $plans,
            'current' => [
                'plan' => $features->plan($workspace)?->name,
                'allowance' => $features->allowance($workspace),
                'engines' => $features->engines($workspace),
                'maxBrands' => $features->maxBrands($workspace),
            ],
        ]);
    }

    public function checkout(Plan $plan, BillingService $billing): RedirectResponse
    {
        $workspace = WorkspaceContext::current()?->workspace();

        if ($workspace === null) {
            abort(403);
        }

        Gate::authorize('editBilling', $workspace);

        $billing->checkout($workspace, $plan);

        return back()->with('success', 'Plan updated: '.$plan->name.'.');
    }
}
