<?php

namespace App\Modules\Analytics\Console;

use App\Mail\WeeklyDigestMail;
use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Monday 08:00 (build plan §5.3): every brand's mention rate and change,
 * share of voice and answers for the week — mailed to the workspace owner.
 */
final class DigestSendCommand extends Command
{
    protected $signature = 'clot:digest-send';

    protected $description = 'Send the weekly AI visibility digest to every workspace owner';

    public function handle(): int
    {
        $sent = 0;

        foreach (Workspace::query()->with('owner')->get() as $workspace) {
            $rows = [];

            foreach (Brand::withoutWorkspaceScope()->where('workspace_id', $workspace->id)->get() as $brand) {
                $current = $this->windowStats($brand->id, now()->subDays(7), now());
                $before = $this->windowStats($brand->id, now()->subDays(14), now()->subDays(7));

                if ($current['total'] === 0) {
                    continue;
                }

                $after = WilsonInterval::compute($current['named'], $current['total']);
                $beforeRate = WilsonInterval::compute($before['named'], $before['total']);

                $trend = match (WilsonInterval::rangesSeparate($beforeRate, $after)) {
                    true => ($after['point'] ?? 0) > ($beforeRate['point'] ?? 0) ? 'Up' : 'Down',
                    false => 'No clear change',
                    null => '—',
                };

                $rows[] = [
                    'name' => $brand->name,
                    'mentionRate' => $after['point'],
                    'low' => $after['low'],
                    'high' => $after['high'],
                    'trend' => $trend,
                    'shareOfVoice' => $current['yourMentions'] > 0
                        ? (int) round($current['yourMentions'] / max($current['yourMentions'] + $current['rivalMentions'], 1) * 100)
                        : null,
                    'answers' => $current['total'],
                ];
            }

            if ($rows === []) {
                continue;
            }

            Mail::to($workspace->owner->email)->send(new WeeklyDigestMail($workspace, $rows));
            $sent++;
        }

        $this->info("Digest sent to {$sent} workspace(s).");

        return self::SUCCESS;
    }

    /**
     * @return array{total: int, named: int, yourMentions: int, rivalMentions: int}
     */
    private function windowStats(int $brandId, CarbonInterface $from, CarbonInterface $to): array
    {
        $answerIds = Answer::withoutWorkspaceScope()
            ->where('brand_id', $brandId)
            ->where('status', AnswerStatus::Collected)
            ->whereBetween('created_at', [$from, $to])
            ->pluck('id');

        $mentions = AnswerMention::withoutWorkspaceScope()->whereIn('answer_id', $answerIds);

        return [
            'total' => $answerIds->count(),
            'named' => Answer::withoutWorkspaceScope()->whereIn('id', $answerIds)->where('named_you', true)->count(),
            'yourMentions' => (clone $mentions)->where('is_you', true)->count(),
            'rivalMentions' => (clone $mentions)->where('is_you', false)->count(),
        ];
    }
}
