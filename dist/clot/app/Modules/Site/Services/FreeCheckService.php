<?php

namespace App\Modules\Site\Services;

use App\Modules\Collection\DTO\CollectRequest;
use App\Modules\Collection\Services\CollectorManager;
use App\Modules\Site\Models\FreeCheck;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The free AI-visibility check: the public lead magnet (build plan §6.3).
 *
 * Budget protection — the check costs real provider answers, so it is capped
 * three ways: per email per day, per IP per day, and globally per day (the
 * operator's daily spend cap on top, enforced inside AnswerJob for sweeps
 * and here via a counter for checks).
 */
final class FreeCheckService
{
    public function __construct(private readonly CollectorManager $collectorManager) {}

    public function run(string $email, string $brandName, string $website, string $offering, string $ipHash): FreeCheck
    {
        $this->guardLimits($email, $ipHash);

        $engine = (string) config('clot-site.free_check_engine', 'chatgpt');
        $today = now()->toDateString();
        $named = 0;
        $cost = 0.0;

        $questions = [
            sprintf('What is the best %s for a small business?', $offering),
            sprintf('Which %s tools would you recommend and why?', $offering),
            sprintf('Top %s options compared', $offering),
        ];

        $answers = [];

        foreach ($questions as $index => $question) {
            $response = $this->collectorManager->collect(new CollectRequest(
                prompt: $question,
                countryCode: 'US',
                languageCode: 'en',
                engine: $engine,
                method: 'demo',
                idempotencyKey: hash('sha256', implode('|', ['free-check', $email, $brandName, $today, $index])),
            ));

            if (! $response->isCollected()) {
                continue;
            }

            $cost += $response->providerCostUsd ?? 0;
            $answers[] = ['question' => $question, 'text' => $response->answerText, 'named' => false];

            foreach (array_merge([$brandName], $this->aliasesFromDomain($website)) as $identity) {
                if ($identity !== '' && stripos((string) $response->answerText, $identity) !== false) {
                    $answers[array_key_last($answers)]['named'] = true;
                    $named++;
                    break;
                }
            }
        }

        $total = count($answers);
        $mentionRate = $total > 0 ? (int) round($named / $total * 100) : 0;

        $check = FreeCheck::query()->create([
            'email' => strtolower($email),
            'brand_name' => $brandName,
            'website' => $website,
            'offering' => $offering,
            'grade' => $this->grade($mentionRate),
            'mention_rate' => $mentionRate,
            'answers_count' => $total,
            'named_count' => $named,
            'cost_usd' => $cost,
            'ip_hash' => $ipHash,
        ]);

        return $check->setAttribute('answers', $answers);
    }

    public function grade(int $mentionRate): string
    {
        return match (true) {
            $mentionRate >= 60 => 'A',
            $mentionRate >= 45 => 'B',
            $mentionRate >= 30 => 'C',
            $mentionRate >= 15 => 'D',
            default => 'F',
        };
    }

    private function guardLimits(string $email, string $ipHash): void
    {
        $perEmail = (int) config('clot-site.free_check_max_per_email', 3);
        $perIp = (int) config('clot-site.free_check_max_per_ip', 5);
        $global = (int) config('clot-site.free_check_max_per_day', 25);

        $limiter = RateLimiter::attempt('free-check:'.strtolower($email), $perEmail, fn () => null, 86400);
        $limiterIp = RateLimiter::attempt('free-check-ip:'.$ipHash, $perIp, fn () => null, 86400);

        if ($limiter === false || $limiterIp === false) {
            throw new \RuntimeException('You have used your free checks for today. Try again tomorrow.');
        }

        if (FreeCheck::query()->whereDate('created_at', now()->toDateString())->count() >= $global) {
            throw new \RuntimeException('The free check is at capacity today. Try again tomorrow.');
        }
    }

    /**
     * The domain itself counts as the brand: "Northwind" is named when
     * northwind.example appears in the answer text.
     *
     * @return list<string>
     */
    private function aliasesFromDomain(string $website): array
    {
        $host = strtolower(trim(parse_url(str_contains($website, '://') ? $website : 'https://'.$website, PHP_URL_HOST) ?? ''));

        if ($host === '') {
            return [];
        }

        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $base = explode('.', $host)[0];

        return strlen($base) >= 3 ? [$base, $host] : [$host];
    }
}
