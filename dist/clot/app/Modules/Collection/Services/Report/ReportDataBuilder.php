<?php

namespace App\Modules\Collection\Services\Report;

use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Billing\Services\PlanFeatures;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerCitation;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Models\Workspace;

/**
 * Builds the report payload shared by the PDF and the public share page —
 * one builder, two renderers, identical numbers.
 */
final class ReportDataBuilder
{
    public function __construct(private readonly PlanFeatures $features) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);

        $collected = Answer::query()
            ->where('brand_id', $brand->id)
            ->where('status', 'collected')
            ->where('created_at', '>=', $since);

        $total = (clone $collected)->count();
        $named = (clone $collected)->where('named_you', true)->count();
        $cited = (clone $collected)->where('cited_you', true)->count();

        $mention = WilsonInterval::compute($named, $total);
        $citation = WilsonInterval::compute($cited, $total);

        $allMentions = AnswerMention::query()->whereIn('answer_id', (clone $collected)->select('id'))->count();
        $yourMentions = AnswerMention::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->where('is_you', true)
            ->count();

        $engines = [];

        foreach ((array) $brand->engines as $engine) {
            $engineTotal = (clone $collected)->where('engine', $engine)->count();

            if ($engineTotal === 0) {
                continue;
            }

            $engineNamed = (clone $collected)->where('engine', $engine)->where('named_you', true)->count();
            $range = WilsonInterval::compute($engineNamed, $engineTotal);

            $engines[] = [
                'engine' => $engine,
                'rate' => $range['point'],
                'low' => $range['low'],
                'high' => $range['high'],
                'answers' => $engineTotal,
            ];
        }

        // Competitor comparison from the same answers.
        $competitors = [];

        foreach ($brand->competitors as $competitor) {
            $rivalMentions = AnswerMention::query()
                ->whereIn('answer_id', (clone $collected)->select('id'))
                ->where('competitor_id', $competitor->id)
                ->count();

            $competitors[] = [
                'name' => $competitor->name,
                'mentionRate' => $total > 0 ? round($rivalMentions / $total * 100) : 0,
            ];
        }

        usort($competitors, fn (array $a, array $b): int => $b['mentionRate'] <=> $a['mentionRate']);

        // Top sources.
        $sourceRows = AnswerCitation::query()
            ->whereIn('answer_id', (clone $collected)->select('id'))
            ->selectRaw('domain, owner_type, COUNT(*) as citations')
            ->groupBy('domain', 'owner_type')
            ->orderByDesc('citations')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'domain' => $row->domain,
                'ownerType' => $row->owner_type,
                'citations' => (int) $row->citations,
            ]);

        /** @var Workspace|null $workspace */
        $workspace = $brand->workspace()->withoutGlobalScopes()->first();

        if ($workspace === null) {
            throw new \RuntimeException('Report brand has no workspace.');
        }

        $whiteLabel = $this->features->flag($workspace, 'white_label');

        return [
            'brand' => ['id' => $brand->id, 'name' => $brand->name],
            'periodDays' => $days,
            'metrics' => [
                'mentionRate' => $mention,
                'citationRate' => $citation,
                'shareOfVoice' => $allMentions > 0 ? (int) round($yourMentions / $allMentions * 100) : null,
                'answersCollected' => $total,
                'lowConfidence' => $total < 30,
            ],
            'engines' => $engines,
            'competitors' => $competitors,
            'sources' => $sourceRows,
            'label' => [
                'name' => $whiteLabel ? $workspace->name : config('app.name'),
                'whiteLabel' => $whiteLabel,
            ],
        ];
    }
}
