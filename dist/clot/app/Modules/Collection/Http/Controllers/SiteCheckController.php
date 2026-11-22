<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Services\Site\SiteCheckService;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website tab: site check (AI crawler access, llms.txt, structured data,
 * content) scored 0-100. Runs on demand — the check is synchronous because
 * it's a few HTTP calls, not a sweep.
 */
final class SiteCheckController extends Controller
{
    public function run(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        $domain = $brand->domains[0] ?? $brand->name;
        $check = app(SiteCheckService::class)->check($domain);

        return Inertia::render('Brands/SiteCheck', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'check' => $check,
        ]);
    }
}
