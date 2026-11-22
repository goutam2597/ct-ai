<?php

namespace App\Modules\Analytics\Services;

use App\Modules\Analytics\Jobs\WebhookDeliveryJob;
use App\Modules\Analytics\Models\AlertChannel;
use App\Modules\Analytics\Models\AlertEvent;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;
use App\Modules\Tenancy\Models\Workspace;
use Carbon\CarbonInterface;

/**
 * The morning check (build plan §5.3): every morning after the sweeps each
 * brand is compared with the week before. Four rules — each one readable,
 * each one sent ONCE (dedup_key unique per workspace+rule+period).
 */
final class AlertEvaluator
{
    private const MIN_SAMPLE = 30;

    public function evaluateAll(): int
    {
        $created = 0;

        foreach (Brand::withoutWorkspaceScope()->where('is_active', true)->get() as $brand) {
            $created += $this->evaluateBrand($brand);
        }

        return $created;
    }

    public function evaluateBrand(Brand $brand): int
    {
        $created = $this->mentionRateDrop($brand)
            + $this->rivalPullAhead($brand)
            + $this->negativeMentions($brand)
            + $this->collectionFailing($brand);

        if ($created > 0) {
            $this->deliver($brand->workspace_id);
        }

        return $created;
    }

    /**
     * Rule 1: this week's range sits entirely below last week's (both >= 30 answers).
     */
    private function mentionRateDrop(Brand $brand): int
    {
        $current = $this->mentionWindow($brand, now()->subDays(7), now());
        $before = $this->mentionWindow($brand, now()->subDays(14), now()->subDays(7));

        if ($current['total'] < self::MIN_SAMPLE || $before['total'] < self::MIN_SAMPLE) {
            return 0;
        }

        $after = WilsonInterval::compute($current['named'], $current['total']);
        $beforeRate = WilsonInterval::compute($before['named'], $before['total']);

        if ($after['point'] === null || $beforeRate['point'] === null) {
            return 0;
        }

        if (WilsonInterval::rangesSeparate($beforeRate, $after) !== true || $after['point'] >= $beforeRate['point']) {
            return 0;
        }

        return $this->record(
            $brand,
            'mention_rate_drop',
            'week-'.now()->format('YW'),
            sprintf('%s: mention rate dropped', $brand->name),
            [
                'before' => ['rate' => $beforeRate['point'], 'low' => $beforeRate['low'], 'high' => $beforeRate['high'], 'n' => $before['total']],
                'after' => ['rate' => $after['point'], 'low' => $after['low'], 'high' => $after['high'], 'n' => $current['total']],
            ],
        );
    }

    /**
     * Rule 2: a rival that was behind is now clearly ahead over the last 7 days.
     */
    private function rivalPullAhead(Brand $brand): int
    {
        $created = 0;

        $current = $this->mentionWindow($brand, now()->subDays(7), now());
        $before = $this->mentionWindow($brand, now()->subDays(14), now()->subDays(7));

        if ($current['total'] < self::MIN_SAMPLE || $before['total'] < self::MIN_SAMPLE) {
            return 0;
        }

        $yours = WilsonInterval::compute($current['named'], $current['total']);
        $yoursBefore = WilsonInterval::compute($before['named'], $before['total']);

        if ($yours['point'] === null || $yoursBefore['point'] === null) {
            return 0;
        }

        $competitors = Competitor::query()->where('brand_id', $brand->id)->get();

        foreach ($competitors as $competitor) {
            $rivalNow = $this->rivalWindow($brand, $competitor, now()->subDays(7), now());
            $rivalBefore = $this->rivalWindow($brand, $competitor, now()->subDays(14), now()->subDays(7));

            $rivalNowRate = WilsonInterval::compute($rivalNow['mentions'], $current['total']);
            $rivalBeforeRate = WilsonInterval::compute($rivalBefore['mentions'], $before['total']);

            if ($rivalNowRate['point'] === null || $rivalBeforeRate['point'] === null) {
                continue;
            }

            $wasBehind = $rivalBeforeRate['point'] < $yoursBefore['point'];
            $isAhead = $rivalNowRate['point'] > $yours['point']
                && WilsonInterval::rangesSeparate($yours, $rivalNowRate) === true;

            if (! $wasBehind || ! $isAhead) {
                continue;
            }

            $created += $this->record(
                $brand,
                'rival_pull_ahead',
                'week-'.now()->format('YW').'-'.$competitor->id,
                sprintf('%s: %s pulled ahead', $brand->name, $competitor->name),
                [
                    'rival' => $competitor->name,
                    'rival_rate' => $rivalNowRate['point'],
                    'your_rate' => $yours['point'],
                ],
            );
        }

        return $created;
    }

    /**
     * Rule 3: the negative share of your mentions rose 15+ points week on week
     * (with 10+ mentions in each week — noisy weeks never wake anyone).
     */
    private function negativeMentions(Brand $brand): int
    {
        $current = $this->mentionWindow($brand, now()->subDays(7), now());
        $before = $this->mentionWindow($brand, now()->subDays(14), now()->subDays(7));

        if ($current['yourMentions'] < 10 || $before['yourMentions'] < 10) {
            return 0;
        }

        $nowShare = $current['negative'] / $current['yourMentions'] * 100;
        $beforeShare = $before['negative'] / $before['yourMentions'] * 100;

        if ($nowShare - $beforeShare < 15) {
            return 0;
        }

        return $this->record(
            $brand,
            'negative_mentions',
            'week-'.now()->format('YW'),
            sprintf('%s: more negative mentions', $brand->name),
            [
                'negative_share_before' => round($beforeShare),
                'negative_share_now' => round($nowShare),
            ],
        );
    }

    /**
     * Rule 4: in the last 24 hours at least 5 answers failed AND they were a
     * fifth or more of all attempts.
     */
    private function collectionFailing(Brand $brand): int
    {
        $collected = Answer::withoutWorkspaceScope()
            ->where('brand_id', $brand->id)
            ->where('status', AnswerStatus::Collected)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $failed = Answer::withoutWorkspaceScope()
            ->where('brand_id', $brand->id)
            ->where('status', AnswerStatus::Failed)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($failed < 5 || $failed / ($failed + $collected) < 0.2) {
            return 0;
        }

        return $this->record(
            $brand,
            'collection_failing',
            'day-'.now()->format('Ymd'),
            sprintf('%s: collection failing', $brand->name),
            ['collected' => $collected, 'failed' => $failed],
        );
    }

    /**
     * @return array{named: int, total: int, negative: int, yourMentions: int}
     */
    private function mentionWindow(Brand $brand, CarbonInterface $from, CarbonInterface $to): array
    {
        $answerIds = Answer::withoutWorkspaceScope()
            ->where('brand_id', $brand->id)
            ->where('status', AnswerStatus::Collected)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $total = $answerIds->count();
        $named = Answer::withoutWorkspaceScope()->whereIn('id', $answerIds)->where('named_you', true)->count();

        $mentions = AnswerMention::withoutWorkspaceScope()
            ->whereIn('answer_id', $answerIds)
            ->where('is_you', true);

        $yourMentions = (clone $mentions)->count();
        $negative = (clone $mentions)->where('tone', Tone::Negative->value)->count();

        return ['named' => $named, 'total' => $total, 'negative' => $negative, 'yourMentions' => $yourMentions];
    }

    /**
     * @return array{mentions: int}
     */
    private function rivalWindow(Brand $brand, Competitor $competitor, CarbonInterface $from, CarbonInterface $to): array
    {
        $mentions = AnswerMention::withoutWorkspaceScope()
            ->whereIn('answer_id', Answer::withoutWorkspaceScope()
                ->where('brand_id', $brand->id)
                ->where('status', AnswerStatus::Collected)
                ->whereBetween('created_at', [$from, $to])
                ->select('id'))
            ->where('competitor_id', $competitor->id)
            ->where('is_you', false)
            ->count();

        return ['mentions' => $mentions];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function record(Brand $brand, string $rule, string $periodKey, string $title, array $body): int
    {
        $dedupKey = substr($rule.':'.$brand->id.':'.$periodKey, 0, 96);

        $event = AlertEvent::withoutWorkspaceScope()->firstOrCreate(
            ['dedup_key' => $dedupKey],
            [
                'workspace_id' => $brand->workspace_id,
                'brand_id' => $brand->id,
                'rule' => $rule,
                'title' => $title,
                'body' => $body,
            ],
        );

        return $event->wasRecentlyCreated ? 1 : 0;
    }

    /** Queue a signed webhook delivery per active channel for this workspace. */
    private function deliver(int $workspaceId): void
    {
        $channels = AlertChannel::withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->where('is_active', true)
            ->where('type', 'webhook')
            ->get();

        foreach ($channels as $channel) {
            WebhookDeliveryJob::dispatch($channel->id);
        }
    }
}
