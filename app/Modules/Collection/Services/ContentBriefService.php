<?php

namespace App\Modules\Collection\Services;

use App\Modules\Analytics\Services\WilsonInterval;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerCitation;
use App\Modules\Collection\Models\AnswerMention;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Prompt;
use Illuminate\Support\Str;

/**
 * Content briefs: for each question you're losing, generate a page outline
 * from the real answers — what the engines say now, who they name, what
 * sources they cite. Written in the brand's language, checked against facts.
 */
final class ContentBriefService
{
    /**
     * Generate content briefs for questions where a rival is clearly ahead.
     *
     * @return array<int, array<string, mixed>>
     */
    public function briefsFor(Brand $brand): array
    {
        $briefs = [];
        $since = now()->subDays(30);
        $collected = Answer::query()
            ->withoutGlobalScopes()
            ->where('brand_id', $brand->id)
            ->where('status', AnswerStatus::Collected->value)
            ->where('created_at', '>=', $since);

        foreach ($brand->prompts()->withoutGlobalScopes()->where('status', 'active')->get() as $prompt) {
            $promptAnswers = (clone $collected)->where('prompt_id', $prompt->id);
            $total = $promptAnswers->count();

            if ($total < 10) {
                continue;
            }

            $named = (clone $promptAnswers)->where('named_you', true)->count();
            $mention = WilsonInterval::compute($named, $total);

            // Only brief questions where you're named in less than half.
            if (($mention['point'] ?? 1) >= 0.5) {
                continue;
            }

            // Who gets named instead.
            $rival = AnswerMention::query()
                ->whereIn('answer_id', (clone $promptAnswers)->select('id'))
                ->where('is_you', false)
                ->selectRaw('competitor_id, COUNT(*) as mentions')
                ->groupBy('competitor_id')
                ->orderByDesc('mentions')
                ->first();

            $rivalName = null;
            if ($rival?->competitor_id) {
                $competitor = $brand->competitors()->withoutGlobalScopes()->where('id', $rival->competitor_id)->first();
                $rivalName = $competitor?->name;
            }

            // Top cited sources for this prompt.
            $sources = AnswerCitation::query()
                ->whereIn('answer_id', (clone $promptAnswers)->select('id'))
                ->selectRaw('domain, COUNT(*) as citations')
                ->groupBy('domain')
                ->orderByDesc('citations')
                ->limit(5)
                ->pluck('domain')
                ->all();

            $sampleAnswer = (clone $promptAnswers)->where('named_you', false)->first()?->answer_text;

            $briefs[] = [
                'prompt_id' => $prompt->id,
                'question' => $prompt->text,
                'topic' => $prompt->topic,
                'mention_rate' => $mention['point'],
                'range' => [$mention['low'], $mention['high']],
                'answers' => $total,
                'named' => $named,
                'rival_named_instead' => $rivalName,
                'cited_sources' => $sources,
                'sample_answer' => Str::limit((string) $sampleAnswer, 300),
                'angle' => $this->angle($brand->name, $rivalName, $prompt->text),
            ];
        }

        usort($briefs, fn (array $a, array $b): int => ($a['mention_rate'] ?? 1) <=> ($b['mention_rate'] ?? 1));

        return $briefs;
    }

    /** A short content angle for the brief, based on what the answers show. */
    private function angle(string $brandName, ?string $rivalName, string $question): string
    {
        if ($rivalName !== null) {
            return sprintf(
                'A comparison page that answers "%s" head-on: %s vs %s, with the features that matter for the buyer, sourced from the sites the engines already cite.',
                $question,
                $brandName,
                $rivalName,
            );
        }

        return sprintf(
            'A page that answers "%s" plainly, with the facts the engines look for: pricing, features and buyer context.',
            $question,
        );
    }
}
