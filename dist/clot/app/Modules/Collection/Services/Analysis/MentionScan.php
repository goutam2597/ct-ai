<?php

namespace App\Modules\Collection\Services\Analysis;

use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\Enums\MatchedBy;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;

/**
 * Reads a stored answer for every tracked brand at once (the brand and its
 * competitors): who is named, by name/alias/domain, where in the list, and
 * with what word-based tone.
 *
 * Deterministic, cost-free, always-on. AI analysis (sentiment/themes) is an
 * optional operator-configured upgrade in a later phase and labels itself.
 */
final class MentionScan
{
    private const POSITIVE_WORDS = [
        'easy', 'easiest', 'best', 'great', 'good', 'excellent', 'reliable', 'fast',
        'affordable', 'simple', 'strong', 'helpful', 'solid', 'recommended', 'popular',
        'quick', 'seamless', 'intuitive', 'robust', 'fair',
    ];

    private const NEGATIVE_WORDS = [
        'expensive', 'pricey', 'slow', 'limited', 'hard', 'difficult', 'clunky',
        'poor', 'weak', 'steep', 'confusing', 'lacking', 'overpriced', 'complex',
    ];

    /**
     * Scan the answer text and cited links for one tracked brand context.
     *
     * @param  list<Competitor>  $competitors
     */
    public function scan(
        Brand $brand,
        string $answerText,
        CollectorResponse $response,
        array $competitors,
    ): ScanResult {
        $entities = [
            ['brand' => $brand, 'isYou' => true, 'identity' => $brand->identityStrings()],
        ];

        foreach ($competitors as $competitor) {
            $entities[] = [
                'brand' => $competitor,
                'isYou' => false,
                'identity' => $competitor->identityStrings(),
            ];
        }

        $mentioned = [];

        foreach ($entities as $entity) {
            $hit = $this->firstMatch($answerText, $entity['identity']);

            if ($hit !== null) {
                $mentioned[] = [
                    'brand' => $entity['brand'],
                    'isYou' => $entity['isYou'],
                    'position' => $hit['position'],
                    'tone' => $this->toneAround($answerText, $hit['offset']),
                    'matched_by' => $hit['matched_by'],
                ];
            }
        }

        usort($mentioned, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        // Position = rank in order of first appearance (1 = named first).
        foreach ($mentioned as $index => $mention) {
            $mentioned[$index]['position'] = $index + 1;
        }

        $youMention = null;
        foreach ($mentioned as $mention) {
            if ($mention['isYou']) {
                $youMention = $mention;

                break;
            }
        }

        $citedYou = false;
        $brandDomains = array_map('strtolower', $brand->domains ?? []);

        foreach ($response->citedLinks as $link) {
            if ($this->domainMatches($link, $brandDomains)) {
                $citedYou = true;

                break;
            }
        }

        return new ScanResult(
            mentioned: $mentioned,
            namedYou: $youMention !== null,
            citedYou: $citedYou,
            yourPosition: $youMention['position'] ?? null,
            yourTone: $youMention['tone'] ?? null,
        );
    }

    /**
     * Earliest occurrence of any identity string, with how it matched.
     * Provenance matters: the display name, an alias and a domain are
     * reported differently on the answers screen.
     *
     * @param  list<string>  $identity  [$name, ...aliases, ...domains]
     * @return array{position: int, offset: int, matched_by: MatchedBy}|null
     */
    private function firstMatch(string $text, array $identity): ?array
    {
        $best = null;

        foreach ($identity as $index => $needle) {
            if ($needle === '') {
                continue;
            }

            $type = $index === 0 ? MatchedBy::Name : (str_contains(strtolower($needle), '.') ? MatchedBy::Domain : MatchedBy::Alias);

            $offset = $this->findCaseInsensitive($text, $needle);

            if ($offset === null) {
                continue;
            }

            if ($best === null || $offset < $best['offset']) {
                $best = ['offset' => $offset, 'matched_by' => $type];
            }
        }

        return $best === null ? null : ['position' => 0, 'offset' => $best['offset'], 'matched_by' => $best['matched_by']];
    }

    private function findCaseInsensitive(string $haystack, string $needle): ?int
    {
        $position = stripos($haystack, $needle);

        return $position === false ? null : $position;
    }

    private function toneAround(string $text, int $offset): Tone
    {
        $window = strtolower(substr($text, max(0, $offset - 40), 120));
        $score = 0;

        foreach (self::POSITIVE_WORDS as $word) {
            if (str_contains($window, $word)) {
                $score++;
            }
        }

        foreach (self::NEGATIVE_WORDS as $word) {
            if (str_contains($window, $word)) {
                $score--;
            }
        }

        return match (true) {
            $score > 0 => Tone::Positive,
            $score < 0 => Tone::Negative,
            default => Tone::Neutral,
        };
    }

    /**
     * @param  list<string>  $domains
     */
    private function domainMatches(string $link, array $domains): bool
    {
        $host = $this->hostOf($link);

        if ($host === null || $domains === []) {
            return false;
        }

        foreach ($domains as $domain) {
            $domain = strtolower(trim(preg_replace('#^https?://#', '', $domain) ?? '', "/ \t"));
            $domain = str_starts_with($domain, 'www.') ? substr($domain, 4) : $domain;

            if ($domain !== '' && ($host === $domain || str_ends_with($host, '.'.$domain))) {
                return true;
            }
        }

        return false;
    }

    private function hostOf(string $link): ?string
    {
        $host = parse_url(strtolower(trim($link)), PHP_URL_HOST);

        if (is_string($host) && $host !== '') {
            return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        }

        // Links may arrive without a scheme ("northwind.example/pricing").
        if (preg_match('#^([a-z0-9.-]+)\.[a-z]{2,}(/|$)#i', trim($link), $m) === 1) {
            $host = strtolower($m[1]);

            return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        }

        return null;
    }
}
