<?php

namespace App\Modules\Collection\Http\Controllers;

use App\Modules\ApiPlatform\Services\ApiRateService;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Services\CommerceReadinessService;
use App\Modules\Collection\Services\DirectoriesService;
use App\Modules\Tenancy\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Brand sub-pages: competitors, sources, perception. Each uses the shared
 * ApiRateService to build the payload (same numbers as the API).
 */
final class BrandTabController extends Controller
{
    public function __construct(private readonly ApiRateService $rates) {}

    public function competitors(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/Competitors', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'comparison' => $this->rates->competitorComparison($brand, 30),
        ]);
    }

    public function sources(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/Sources', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'sources' => $this->rates->topSources($brand, 30),
        ]);
    }

    public function perception(Brand $brand): Response
    {
        Gate::authorize('view', $brand);

        $since = now()->subDays(30);
        $collected = Answer::query()
            ->where('brand_id', $brand->id)
            ->where('status', 'collected')
            ->where('created_at', '>=', $since);

        $total = (clone $collected)->count();
        $positive = (clone $collected)->where('tone', 'positive')->count();
        $neutral = (clone $collected)->where('tone', 'neutral')->count();
        $negative = (clone $collected)->where('tone', 'negative')->count();

        $yourMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true);

        $positiveMentions = (clone $yourMentions)->where('tone', 'positive')->count();
        $negativeMentions = (clone $yourMentions)->where('tone', 'negative')->count();

        $toneByEngine = [];
        foreach ((array) $brand->engines as $engine) {
            $engineCollected = (clone $collected)->where('engine', $engine)->where('named_you', true)->count();
            $enginePositive = (clone $collected)->where('engine', $engine)->where('tone', 'positive')->count();

            if ($engineCollected === 0) {
                continue;
            }

            $toneByEngine[] = [
                'engine' => $engine,
                'positive' => (int) round($enginePositive / $engineCollected * 100),
                'mentions' => $engineCollected,
            ];
        }

        $themes = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true)
            ->selectRaw('COALESCE(competitor_id, brand_id) as entity_id, tone, COUNT(*) as mentions')
            ->groupBy('entity_id', 'tone')
            ->orderByDesc('mentions')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'tone' => $row->tone,
                'mentions' => (int) $row->mentions,
            ]);

        return Inertia::render('Brands/Perception', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'tone' => [
                'positive' => $total > 0 ? (int) round($positive / $total * 100) : 0,
                'neutral' => $total > 0 ? (int) round($neutral / $total * 100) : 0,
                'negative' => $total > 0 ? (int) round($negative / $total * 100) : 0,
            ],
            'yourMentions' => [
                'positive' => $positiveMentions,
                'negative' => $negativeMentions,
            ],
            'toneByEngine' => $toneByEngine,
            'themes' => $themes,
            'engines' => (array) $brand->engines,
        ]);
    }

    public function directories(Brand $brand, DirectoriesService $service): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/Directories', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'directories' => $service->rankFor($brand, 30),
        ]);
    }

    public function commerce(Brand $brand, CommerceReadinessService $service): Response
    {
        Gate::authorize('view', $brand);

        return Inertia::render('Brands/Commerce', [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'readiness' => $service->score($brand),
        ]);
    }
}
