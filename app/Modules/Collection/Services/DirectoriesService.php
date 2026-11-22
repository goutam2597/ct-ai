<?php

namespace App\Modules\Collection\Services;

use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\AnswerCitation;
use App\Modules\Collection\Models\Brand;
use Illuminate\Support\Str;

/**
 * Directories: which review/directory sites the engines cite, whether rivals
 * are listed there and you are not, and AI-written submission kits.
 *
 * CLOT never auto-submits — these sites forbid automated submissions. The
 * service ranks them and writes the text to submit; the user submits manually.
 */
final class DirectoriesService
{
    /** The global directory/review-site catalogue, grouped by type. */
    public const CATALOGUE = [
        'B2B software reviews' => [
            ['name' => 'G2', 'domain' => 'g2.com', 'cost' => 'Free, paid extras'],
            ['name' => 'Capterra', 'domain' => 'capterra.com', 'cost' => 'Free, paid extras'],
            ['name' => 'GetApp', 'domain' => 'getapp.com', 'cost' => 'Free, paid extras'],
            ['name' => 'Software Advice', 'domain' => 'softwareadvice.com', 'cost' => 'Free, paid extras'],
            ['name' => 'TrustRadius', 'domain' => 'trustradius.com', 'cost' => 'Free, paid extras'],
        ],
        'General reviews' => [
            ['name' => 'Trustpilot', 'domain' => 'trustpilot.com', 'cost' => 'Free, paid extras'],
        ],
        'Business profiles' => [
            ['name' => 'Google Business Profile', 'domain' => 'google.com/maps', 'cost' => 'Free'],
            ['name' => 'Bing Places', 'domain' => 'bing.com/places', 'cost' => 'Free'],
            ['name' => 'Apple Business Connect', 'domain' => 'maps.apple.com', 'cost' => 'Free'],
            ['name' => 'Yelp', 'domain' => 'yelp.com', 'cost' => 'Free, paid extras'],
            ['name' => 'LinkedIn company page', 'domain' => 'linkedin.com/company', 'cost' => 'Free'],
        ],
        'Knowledge bases' => [
            ['name' => 'Wikidata', 'domain' => 'wikidata.org', 'cost' => 'Free'],
            ['name' => 'Crunchbase', 'domain' => 'crunchbase.com', 'cost' => 'Free, paid extras'],
        ],
        'Launch sites and communities' => [
            ['name' => 'Product Hunt', 'domain' => 'producthunt.com', 'cost' => 'Free'],
            ['name' => 'AlternativeTo', 'domain' => 'alternativeto.net', 'cost' => 'Free'],
            ['name' => 'SaaSHub', 'domain' => 'saashub.com', 'cost' => 'Free, paid extras'],
        ],
        'Agencies and service providers' => [
            ['name' => 'Clutch', 'domain' => 'clutch.co', 'cost' => 'Free, paid extras'],
        ],
        'Marketplaces' => [
            ['name' => 'Amazon', 'domain' => 'amazon.com', 'cost' => 'Paid'],
            ['name' => 'Etsy', 'domain' => 'etsy.com', 'cost' => 'Paid'],
        ],
    ];

    /**
     * Rank directories for a brand: how often cited, whether rivals appear
     * there without you.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rankFor(Brand $brand, int $days = 30): array
    {
        $since = now()->subDays($days);
        $answerIds = Answer::query()
            ->where('brand_id', $brand->id)
            ->where('status', 'collected')
            ->where('created_at', '>=', $since)
            ->pluck('id');

        $citations = AnswerCitation::query()
            ->whereIn('answer_id', $answerIds)
            ->selectRaw('domain, owner_type, COUNT(*) as total')
            ->groupBy('domain', 'owner_type')
            ->get()
            ->keyBy('domain');

        $ranked = [];

        foreach (self::CATALOGUE as $category => $directories) {
            foreach ($directories as $directory) {
                $domain = $directory['domain'];
                $citation = $citations->get($domain);
                $citedCount = $citation ? (int) $citation->total : 0;

                // Gap: rivals cited here, you not (from citations that mention this domain).
                $rivalCitations = AnswerCitation::query()
                    ->whereIn('answer_id', $answerIds)
                    ->where('domain', $domain)
                    ->where('owner_type', 'competitor')
                    ->count();
                $yourCitations = AnswerCitation::query()
                    ->whereIn('answer_id', $answerIds)
                    ->where('domain', $domain)
                    ->where('owner_type', 'you')
                    ->count();

                $gap = $rivalCitations - $yourCitations;

                $priority = 'Low';
                if ($citedCount >= 100 && $gap > 0) {
                    $priority = 'High';
                } elseif ($citedCount >= 30) {
                    $priority = 'Medium';
                }

                $ranked[] = [
                    'name' => $directory['name'],
                    'domain' => $domain,
                    'category' => $category,
                    'cost' => $directory['cost'],
                    'priority' => $priority,
                    'cited_in_answers' => $citedCount,
                    'rival_gap' => $gap,
                    'gap_detail' => $gap > 0
                        ? sprintf('Rivals cited here %d×, you %d× — %d more', $rivalCitations, $yourCitations, $gap)
                        : null,
                ];
            }
        }

        usort($ranked, fn (array $a, array $b): int => $b['cited_in_answers'] <=> $a['cited_in_answers']);

        return $ranked;
    }

    /**
     * Generate an AI-written submission kit for one directory.
     *
     * @return array<string, string>
     */
    public function submissionKit(Brand $brand, string $directoryName): array
    {
        $description = sprintf(
            '%s is a %s. Visit %s to learn more.',
            $brand->name,
            $brand->settings['tagline'] ?? 'trusted solution',
            $brand->domains[0] ?? $brand->name,
        );

        return [
            'directory' => $directoryName,
            'brand' => $brand->name,
            'short_description' => Str::limit($description, 160),
            'long_description' => $description,
            'website' => 'https://'.($brand->domains[0] ?? $brand->name),
            'note' => 'These sites forbid automated submissions. Submit manually, then mark this kit as submitted.',
        ];
    }
}
