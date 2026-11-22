<?php

namespace App\Modules\Collection\Services\Analysis;

use App\Modules\Collection\Enums\OwnerType;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;

/**
 * Classifies every cited link as yours, a competitor's, or a third party's.
 * Tracking params and "www." are stripped first so one page counts once.
 */
final class CitationScan
{
    /**
     * @param  list<Competitor>  $competitors
     * @return list<array{url: string, domain: string, owner_type: OwnerType, competitor_id: int|null}>
     */
    public function classify(string $url, Brand $brand, array $competitors): array
    {
        $domain = $this->domainOf($url);

        if ($domain === null) {
            return [];
        }

        foreach ($brand->domains ?? [] as $ownDomain) {
            if ($this->sameDomain($domain, $ownDomain)) {
                return [['url' => $url, 'domain' => $domain, 'owner_type' => OwnerType::You, 'competitor_id' => null]];
            }
        }

        foreach ($competitors as $competitor) {
            foreach ($competitor->domains ?? [] as $competitorDomain) {
                if ($this->sameDomain($domain, $competitorDomain)) {
                    return [['url' => $url, 'domain' => $domain, 'owner_type' => OwnerType::Competitor, 'competitor_id' => $competitor->id]];
                }
            }
        }

        return [['url' => $url, 'domain' => $domain, 'owner_type' => OwnerType::ThirdParty, 'competitor_id' => null]];
    }

    /**
     * @param  list<string>  $citedLinks
     * @param  list<Competitor>  $competitors
     * @return list<array{url: string, domain: string, owner_type: OwnerType, competitor_id: int|null}>
     */
    public function classifyAll(array $citedLinks, Brand $brand, array $competitors): array
    {
        $seen = [];
        $out = [];

        foreach ($citedLinks as $link) {
            $results = $this->classify((string) $link, $brand, $competitors);

            foreach ($results as $result) {
                if (! in_array($result['domain'], $seen, true)) {
                    $seen[] = $result['domain'];
                    $out[] = $result;
                }
            }
        }

        return $out;
    }

    private function sameDomain(string $host, string $candidate): bool
    {
        $candidate = strtolower(trim((string) preg_replace('#^https?://#', '', trim($candidate)), "/ \t"));
        $candidate = str_starts_with($candidate, 'www.') ? substr($candidate, 4) : $candidate;

        if ($candidate === '') {
            return false;
        }

        return $host === $candidate || str_ends_with($host, '.'.$candidate);
    }

    private function domainOf(string $url): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return null;
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.$url;
        }

        $host = parse_url(strtolower($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;

        // A real hostname: labels of letters/digits/hyphens ending in a TLD.
        // Rejects prose fragments that parse_url happily reports as "hosts".
        if (preg_match('/^([a-z0-9-]+\.)+[a-z]{2,}$/', $host) !== 1) {
            return null;
        }

        return $host;
    }
}
