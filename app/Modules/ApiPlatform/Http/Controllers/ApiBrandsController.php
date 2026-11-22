<?php

namespace App\Modules\ApiPlatform\Http\Controllers;

use App\Modules\ApiPlatform\Services\ApiRateService;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * REST API v1 (build plan §6.1 API row). Every route requires the api.key
 * middleware; brands from another workspace simply do not exist for the key
 * (404, never 403 — existence is not leaked).
 */
final class ApiBrandsController extends Controller
{
    public function index(ApiRateService $rates): JsonResponse
    {
        $brands = Brand::query()
            ->orderBy('id')
            ->get()
            ->map(fn (Brand $brand) => $rates->brandSummary($brand));

        return response()->json(['data' => $brands]);
    }

    public function show(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        return response()->json(['data' => [
            ...$rates->brandSummary($brand),
            'overview' => $rates->brandOverview($brand, $this->days($request)),
        ]]);
    }

    public function prompts(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        return response()->json(['data' => $rates->promptPerformance($brand, $this->days($request))]);
    }

    public function competitors(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        return response()->json(['data' => $rates->competitorComparison($brand, $this->days($request))]);
    }

    public function sources(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        return response()->json(['data' => $rates->topSources($brand, $this->days($request))]);
    }

    public function answers(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        $named = $request->filled('named') ? $request->boolean('named') : null;

        return response()->json($rates->pagedAnswers(
            brand: $brand,
            days: $this->days($request),
            engine: $request->filled('engine') ? (string) $request->string('engine') : null,
            named: $named,
            perPage: (int) $request->input('per_page', 50),
            page: (int) $request->input('page', 1),
        ));
    }

    public function opportunities(int $brandId, Request $request, ApiRateService $rates): JsonResponse
    {
        $brand = $this->findBrand($brandId);

        return response()->json(['data' => $rates->opportunities($brand, $this->days($request))]);
    }

    /** Write endpoint: add questions (up to 50 per request). Member+ roles only. */
    public function addPrompts(int $brandId, Request $request): JsonResponse
    {
        /** @var WorkspaceRole|null $role */
        $role = $request->attributes->get('api_role');

        if ($role === null || ! $role->canEditResults()) {
            return response()->json(['error' => 'This API key cannot add questions.'], 403);
        }

        $brand = $this->findBrand($brandId);

        $validated = $request->validate([
            'prompts' => ['required', 'array', 'min:1', 'max:50'],
            'prompts.*' => ['required', 'string', 'min:8', 'max:300', 'distinct'],
            'topic' => ['nullable', 'string', 'max:64'],
        ]);

        $created = 0;

        foreach ($validated['prompts'] as $text) {
            $exists = Prompt::query()
                ->where('brand_id', $brand->id)
                ->where('text', $text)
                ->exists();

            if ($exists) {
                continue;
            }

            Prompt::query()->create([
                'workspace_id' => $brand->workspace_id,
                'brand_id' => $brand->id,
                'text' => $text,
                'topic' => $validated['topic'] ?? null,
                'status' => 'active',
            ]);

            $created++;
        }

        return response()->json([
            'data' => [
                'created' => $created,
                'skipped_duplicates' => count($validated['prompts']) - $created,
            ],
        ], Response::HTTP_CREATED);
    }

    /** Foreign brands 404 — existence is never leaked over the API. */
    private function findBrand(int $brandId): Brand
    {
        $context = WorkspaceContext::current();

        $brand = Brand::query()
            ->withoutGlobalScopes()
            ->where('id', $brandId)
            ->where('workspace_id', $context?->workspace()?->id)
            ->first();

        if ($brand === null) {
            abort(Response::HTTP_NOT_FOUND, 'Brand not found.');
        }

        return $brand;
    }

    private function days(Request $request): int
    {
        $days = (int) $request->input('days', 30);

        return in_array($days, [7, 30, 90], true) ? $days : 30;
    }
}
