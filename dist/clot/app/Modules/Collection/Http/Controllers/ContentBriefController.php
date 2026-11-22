<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Services\ContentBriefService;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class ContentBriefController extends Controller
{
    public function index(Brand $brand, ContentBriefService $briefs): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/ContentBriefs', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'briefs' => $briefs->briefsFor($brand),
        ]);
    }
}
