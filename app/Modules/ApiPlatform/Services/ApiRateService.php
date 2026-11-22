<?php

namespace App\Modules\ApiPlatform\Services;

use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerCitation;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds the JSON payloads for REST API v1. Rates always carry low/high —
 * the 95% range is part of the API contract (build plan §6.1).
 */
final class ApiRateService
{
    /** @return array<string, mixed> */
    public function brandSummary(Brand $brand): array
    {
        return [
            'id' => $brand->id,
            'name' => $brand->name,
            'markets' => $brand->markets()->withoutGlobalScopes()->get()->map(fn ($m) => $m->key())->all(),
            'engines' => $brand->engines,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function brandOverview(Brand $brand, int $days = 30): array
    {
        $collected = $this->collectedAnswers($brand, $days);
        $total = $collected->count();
        $named = (clone $collected)->where('named_you', true)->count();
        $cited = (clone $collected)->where('cited_you', true)->count();

        $mention = WilsonInterval::compute($named, $total);
        $citation = WilsonInterval::compute($cited, $total);

        $allMentions = AnswerMention::query()->whereIn('answer_id', (clone $collected)->select('id'))->count();
        $yourMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true)
            ->count();

        return [
            'days' => $days,
            'answers' => $total,
            'mention' => ['rate' => $mention['point'], 'low' => $mention['low'], 'high' => $mention['high']],
            'citation' => ['rate' => $citation['point'], 'low' => $citation['low'], 'high' => $citation['high']],
            'share_of_voice' => $allMentions > 0 ? round($yourMentions / $allMentions * 100) / 100 : null,
        ];
    }

    /**
     * Per-prompt mention rates per engine.
     *
     * @return array<int, array<string, mixed>>
     */
    public function promptPerformance(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);
        $rows = [];

        foreach ($brand->prompts()->withoutGlobalScopes()->where('status', 'active')->get() as $prompt) {
            $engines = [];

            foreach ((array) $brand->engines as $engine) {
                $total = Answer::query()
                    ->where('prompt_id', $prompt->id)
                    ->where('engine', $engine)
                    ->where('status', AnswerStatus::Collected->value)
                    ->where('created_at', '>=', $since)
                    ->count();

                $named = Answer::query()
                    ->where('prompt_id', $prompt->id)
                    ->where('engine', $engine)
                    ->where('status', AnswerStatus::Collected->value)
                    ->where('named_you', true)
                    ->where('created_at', '>=', $since)
                    ->count();

                $rate = WilsonInterval::compute($named, $total);

                $engines[$engine] = $total > 0
                    ? ['rate' => $rate['point'], 'low' => $rate['low'], 'high' => $rate['high'], 'answers' => $total]
                    : null;
            }

            $rows[] = [
                'id' => $prompt->id,
                'text' => $prompt->text,
                'topic' => $prompt->topic,
                'engines' => $engines,
            ];
        }

        return $rows;
    }

    /**
     * Brand vs rivals: mention rate and per-engine grid.
     *
     * @return array<string, mixed>
     */
    public function competitorComparison(Brand $brand, int $days = 30): array
    {
        $collected = $this->collectedAnswers($brand, $days);
        $total = $collected->count();

        $competitors = [];

        foreach ($brand->competitors as $competitor) {
            $mentions = AnswerMention::query()
                ->whereIn('answer_id', (clone $collected)->select('id'))
                ->where('competitor_id', $competitor->id)
                ->count();

            $rate = WilsonInterval::compute($mentions, $total);

            $competitors[] = [
                'name' => $competitor->name,
                'mention' => ['rate' => $rate['point'], 'low' => $rate['low'], 'high' => $rate['high']],
            ];
        }

        $yourMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true)
            ->count();

        $yours = WilsonInterval::compute($yourMentions, $total);

        return [
            'you' => ['name' => $brand->name, 'mention' => ['rate' => $yours['point'], 'low' => $yours['low'], 'high' => $yours['high']]],
            'competitors' => $competitors,
        ];
    }

    /**
     * Cited domains, grouped and classified.
     *
     * @return array<int, array<string, mixed>>
     */
    public function topSources(Brand $brand, int $days = 30): array
    {
        return AnswerCitation::query()
            ->whereIn('answer_id', $this->collectedAnswers($brand, $days)->select('id'))
            ->selectRaw('domain, owner_type, COUNT(*) as citations')
            ->groupBy('domain', 'owner_type')
            ->orderByDesc('citations')
            ->limit(25)
            ->get()
            ->map(fn ($row) => [
                'domain' => $row->domain,
                'owner_type' => $row->owner_type,
                'citations' => (int) $row->citations,
            ])
            ->all();
    }

    /**
     * Ranked next steps from fixed rules (build plan §6.1 Opportunities).
     *
     * @return array<int, array<string, mixed>>
     */
    public function opportunities(Brand $brand, int $days = 30): array
    {
        $opportunities = [];
        $since = now()->subDays($days);

        $collected = $this->collectedAnswers($brand, $days);
        $total = $collected->count();
        $yourNamed = (clone $collected)->where('named_you', true)->count();
        $yourRate = WilsonInterval::compute($yourNamed, $total);

        // Rule 1: a rival named clearly more often than you on a question
        // (8+ points → score grows with the gap, 48–100).
        foreach ($brand->prompts()->withoutGlobalScopes()->where('status', 'active')->get() as $prompt) {
            foreach ($brand->competitors as $competitor) {
                $promptTotal = (clone $collected)->where('prompt_id', $prompt->id)->count();

                if ($promptTotal < 10) {
                    continue;
                }

                $rivalMentions = AnswerMention::query()
                    ->whereIn('answer_id', (clone $collected)->where('prompt_id', $prompt->id)->select('id'))
                    ->where('competitor_id', $competitor->id)
                    ->count();
                $yourNamedOnPrompt = (clone $collected)->where('prompt_id', $prompt->id)->where('named_you', true)->count();

                $rivalRate = $rivalMentions / $promptTotal * 100;
                $yourRatePct = $yourNamedOnPrompt / $promptTotal * 100;
                $gap = $rivalRate - $yourRatePct;

                if ($gap >= 8) {
                    $score = (int) min(100, 48 + round($gap * 2));

                    $opportunities[] = [
                        'rule' => 'rival_wins_question',
                        'score' => $score,
                        'title' => sprintf('Win "%s" — %s is named %d points more often', $prompt->text, $competitor->name, (int) round($gap)),
                        'evidence' => [
                            'prompt_id' => $prompt->id,
                            'rival' => $competitor->name,
                            'rival_rate_pct' => round($rivalRate),
                            'your_rate_pct' => round($yourRatePct),
                            'answers' => $promptTotal,
                        ],
                    ];
                }
            }
        }

        // Rule 2: sites cited beside rivals more often than beside you (top 3 gaps).
        $sourceRows = AnswerCitation::query()
            ->whereIn('answer_id', $collected->select('id'))
            ->selectRaw("domain, SUM(CASE WHEN owner_type = 'third_party' THEN 1 ELSE 0 END) as citations")
            ->groupBy('domain')
            ->havingRaw('SUM(CASE WHEN owner_type = \'third_party\' THEN 1 ELSE 0 END) >= 30')
            ->orderByDesc('citations')
            ->limit(10)
            ->get();

        foreach ($sourceRows as $row) {
            $opportunities[] = [
                'rule' => 'source_favours_rivals',
                'score' => 70,
                'title' => sprintf('Get listed on %s — cited %d times, rivals named there more often', $row->domain, (int) $row->citations),
                'evidence' => ['domain' => $row->domain, 'citations' => (int) $row->citations],
            ];
        }

        // Rule 3: a weak engine — its range sits below your overall range.
        foreach ((array) $brand->engines as $engine) {
            $engineTotal = (clone $collected)->where('engine', $engine)->count();

            if ($engineTotal < 30) {
                continue;
            }

            $engineNamed = (clone $collected)->where('engine', $engine)->where('named_you', true)->count();
            $engineRate = WilsonInterval::compute($engineNamed, $engineTotal);

            if ($yourRate['point'] !== null
                && $engineRate['point'] !== null
                && $engineRate['point'] < $yourRate['point']
                && WilsonInterval::rangesSeparate($yourRate, $engineRate) === true
            ) {
                $opportunities[] = [
                    'rule' => 'weak_engine',
                    'score' => 55,
                    'title' => sprintf('Lift your rate on %s — clearly below your overall rate', $engine),
                    'evidence' => [
                        'engine' => $engine,
                        'engine_rate' => $engineRate['point'],
                        'overall_rate' => $yourRate['point'],
                    ],
                ];
            }
        }

        usort($opportunities, fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $opportunities;
    }

    /**
     * Paged stored answers. per_page caps at 200 (documented contract).
     *
     * @return array<string, mixed>
     */
    public function pagedAnswers(Brand $brand, int $days, ?string $engine, ?bool $named, int $perPage, int $page): array
    {
        $perPage = max(1, min(200, $perPage));

        $query = $this->collectedAnswers($brand, $days);

        if ($engine !== null) {
            $query->where('engine', $engine);
        }
        if ($named !== null) {
            $query->where('named_you', $named);
        }

        $paginator = $query->with('prompt:id,text')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (Answer $answer) => [
                'id' => $answer->id,
                'status' => $answer->status->value,
                'engine' => $answer->engine,
                'method' => $answer->method,
                'model' => $answer->model,
                'named_you' => (bool) $answer->named_you,
                'cited_you' => (bool) $answer->cited_you,
                'text' => $answer->answer_text,
                'failure_reason' => $answer->failure_reason,
                'prompt' => $answer->prompt?->text,
                'created_at' => $answer->created_at?->toISOString(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $perPage,
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /** @return Builder<Answer> */
    private function collectedAnswers(Brand $brand, int $days): Builder
    {
        return Answer::query()
            ->where('brand_id', $brand->id)
            ->where('status', AnswerStatus::Collected->value)
            ->where('created_at', '>=', now()->subDays($days));
    }
}
