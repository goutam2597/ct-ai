<?php

namespace App\Modules\ApiPlatform\Http\Controllers;

use App\Modules\ApiPlatform\Services\ApiRateService;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * MCP server (build plan §6.1): JSON-RPC 2.0 over HTTP at /mcp/visibility.
 *
 * Same Bearer-key authentication as REST (api.key middleware); seven
 * read-only tools — the assistant can look up brands, rates, rivals,
 * sources and next steps, and search answers. It cannot change anything.
 */
final class McpController extends Controller
{
    public function handle(Request $request, ApiRateService $rates): JsonResponse
    {
        $payload = $request->json()->all();

        if (! isset($payload['method'])) {
            return $this->error(null, -32600, 'Invalid Request');
        }

        $id = $payload['id'] ?? null;
        $method = (string) $payload['method'];
        $params = is_array($payload['params'] ?? null) ? $payload['params'] : [];

        $result = match ($method) {
            'initialize' => $this->initialize(),
            'notifications/initialized' => null,
            'ping' => [],
            'tools/list' => $this->toolsList(),
            'tools/call' => $this->toolsCall($params, $rates),
            default => null,
        };

        if ($method === 'notifications/initialized') {
            return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => []]);
        }

        if ($result === null) {
            return $this->error($id, -32601, "Unknown method: {$method}");
        }

        return response()->json(['jsonrpc' => '2.0', 'id' => $id, 'result' => $result]);
    }

    /**
     * @return array<string, mixed>
     */
    private function initialize(): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => ['tools' => []],
            'serverInfo' => [
                'name' => 'clot-ai-visibility',
                'version' => '1.0.0',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolsList(): array
    {
        $tool = fn (string $name, string $description, array $properties = []): array => [
            'name' => $name,
            'description' => $description,
            'inputSchema' => [
                'type' => 'object',
                'properties' => $properties === [] ? new \stdClass : $properties,
            ],
        ];

        return [
            'tools' => [
                $tool('list_brands', 'List the brands tracked in this workspace.'),
                $tool('brand_overview', 'Mention rate (with 95% range), citation rate, share of voice and answers for one brand.', [
                    'brand_id' => ['type' => 'integer'],
                    'days' => ['type' => 'integer', 'enum' => [7, 30, 90]],
                ]),
                $tool('prompt_performance', 'How each question is doing, per engine.', [
                    'brand_id' => ['type' => 'integer'],
                    'days' => ['type' => 'integer', 'enum' => [7, 30, 90]],
                ]),
                $tool('competitor_comparison', 'The brand against every rival, mention rate with ranges.', [
                    'brand_id' => ['type' => 'integer'],
                ]),
                $tool('top_sources', 'The domains the engines cite, classified yours/competitor/third-party.', [
                    'brand_id' => ['type' => 'integer'],
                ]),
                $tool('next_steps', 'Ranked opportunities with the numbers behind them.', [
                    'brand_id' => ['type' => 'integer'],
                ]),
                $tool('search_answers', 'Search stored answer texts. Returns matching answers with engine and verdict.', [
                    'brand_id' => ['type' => 'integer'],
                    'query' => ['type' => 'string'],
                ]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function toolsCall(array $params, ApiRateService $rates): array
    {
        $name = (string) ($params['name'] ?? '');
        $arguments = is_array($params['arguments'] ?? null) ? $params['arguments'] : [];
        $brandId = isset($arguments['brand_id']) ? (int) $arguments['brand_id'] : null;

        if (in_array($name, ['brand_overview', 'prompt_performance', 'competitor_comparison', 'top_sources', 'next_steps', 'search_answers'], true) && $brandId === null) {
            return $this->toolError("The {$name} tool needs brand_id.");
        }

        $days = in_array((int) ($arguments['days'] ?? 30), [7, 30, 90], true)
            ? (int) ($arguments['days'] ?? 30)
            : 30;

        $brand = null;

        if ($brandId !== null) {
            $context = WorkspaceContext::current();
            $brand = Brand::query()
                ->withoutGlobalScopes()
                ->where('id', $brandId)
                ->where('workspace_id', $context?->workspace()?->id)
                ->first();

            if ($brand === null) {
                return $this->toolError('Brand not found in this workspace.');
            }
        }

        $text = match ($name) {
            'list_brands' => $this->renderListBrands(),
            'brand_overview' => $this->renderOverview($rates, $brand, $days),
            'prompt_performance' => $this->renderPrompts($rates, $brand, $days),
            'competitor_comparison' => $this->renderCompetitors($rates, $brand, $days),
            'top_sources' => $this->renderSources($rates, $brand, $days),
            'next_steps' => $this->renderNextSteps($rates, $brand, $days),
            'search_answers' => $this->renderSearch($brand, (string) ($arguments['query'] ?? '')),
            default => null,
        };

        if ($text === null) {
            return $this->toolError("Unknown tool: {$name}");
        }

        return [
            'content' => [
                ['type' => 'text', 'text' => $text],
            ],
            'isError' => false,
        ];
    }

    private function renderListBrands(): string
    {
        $brands = Brand::query()->orderBy('id')->get()
            ->map(fn (Brand $brand) => "#{$brand->id} {$brand->name} — ".implode(', ', (array) $brand->engines));

        return $brands->isEmpty()
            ? 'No brands tracked in this workspace yet.'
            : "Tracked brands:\n".$brands->implode("\n");
    }

    private function renderOverview(ApiRateService $rates, Brand $brand, int $days): string
    {
        $overview = $rates->brandOverview($brand, $days);
        $mention = $overview['mention'];

        return sprintf(
            "%s — last %d days\nAnswers collected: %d\nMention rate: %s (95%% range %s–%s)\nCitation rate: %s\nShare of voice: %s",
            $brand->name,
            $days,
            $overview['answers'],
            $this->pct($mention['rate']),
            $this->pct($mention['low']),
            $this->pct($mention['high']),
            $this->pct($overview['citation']['rate']),
            $overview['share_of_voice'] === null ? '—' : round($overview['share_of_voice'] * 100).'%',
        );
    }

    private function renderPrompts(ApiRateService $rates, Brand $brand, int $days): string
    {
        $lines = [];

        foreach ($rates->promptPerformance($brand, $days) as $prompt) {
            $rates = array_filter((array) $prompt['engines']);
            $overall = $rates !== [] ? collect($rates)->avg('rate') : null;
            $lines[] = sprintf('%s — overall %s', $prompt['text'], $this->pct($overall));
        }

        return $lines === [] ? 'No active prompts.' : implode("\n", $lines);
    }

    private function renderCompetitors(ApiRateService $rates, Brand $brand, int $days): string
    {
        $comparison = $rates->competitorComparison($brand, $days);
        $lines = [];

        $yours = $comparison['you']['mention'];
        $lines[] = sprintf('%s (YOU): %s', $comparison['you']['name'], $this->pct($yours['rate']));

        foreach ($comparison['competitors'] as $competitor) {
            $lines[] = sprintf('%s: %s', $competitor['name'], $this->pct($competitor['mention']['rate']));
        }

        return implode("\n", $lines);
    }

    private function renderSources(ApiRateService $rates, Brand $brand, int $days): string
    {
        $sources = $rates->topSources($brand, $days);

        if ($sources === []) {
            return 'No citations recorded in this period.';
        }

        return implode("\n", array_map(
            fn (array $source): string => sprintf('%s — %d citations (%s)', $source['domain'], $source['citations'], $source['owner_type']),
            $sources,
        ));
    }

    private function renderNextSteps(ApiRateService $rates, Brand $brand, int $days): string
    {
        $opportunities = $rates->opportunities($brand, $days);

        if ($opportunities === []) {
            return 'No opportunities found — the answers look balanced right now.';
        }

        return implode("\n", array_map(
            fn (array $opportunity, int $i): string => sprintf('%d. [%d] %s', $i + 1, $opportunity['score'], $opportunity['title']),
            $opportunities,
            array_keys($opportunities),
        ));
    }

    private function renderSearch(Brand $brand, string $query): string
    {
        if (trim($query) === '') {
            return 'Provide a search query.';
        }

        $answers = Answer::query()
            ->withoutGlobalScopes()
            ->where('brand_id', $brand->id)
            ->where('status', 'collected')
            ->where('answer_text', 'like', '%'.addcslashes($query, '%_').'%')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'engine', 'answer_text', 'named_you']);

        if ($answers->isEmpty()) {
            return "No stored answers match \"{$query}\".";
        }

        return $answers->map(fn ($answer): string => sprintf(
            '[%s · %s] %s',
            $answer->engine,
            $answer->named_you ? 'named you' : 'not named',
            Str::limit((string) $answer->answer_text, 200),
        ))->implode("\n\n");
    }

    private function pct(?float $value): string
    {
        return $value === null ? '—' : round($value * 100).'%';
    }

    /** @return array<string, mixed> */
    private function toolError(string $message): array
    {
        return [
            'content' => [['type' => 'text', 'text' => $message]],
            'isError' => true,
        ];
    }

    private function error(?int $id, int $code, string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => ['code' => $code, 'message' => $message],
        ]);
    }
}
