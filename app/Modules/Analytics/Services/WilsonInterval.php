<?php

namespace App\Modules\Analytics\Services;

/**
 * Wilson score interval at 95% confidence — the only range CLOT reports.
 *
 * Chosen because it stays reliable with small samples and rates near 0% or
 * 100%, where the normal approximation breaks (methodology page, ADR-0002 in
 * the build plan). All math is deterministic and covered by golden tests.
 */
final class WilsonInterval
{
    public const Z_95 = 1.959963985;

    /**
     * @param  int  $successes  Count of "yes" outcomes (e.g. answers naming the brand).
     * @param  int  $total  Count of collected answers in the sample. Must be >= successes.
     * @return array{low: float|null, high: float|null, point: float|null} Rates in [0,1]; nulls when total is 0.
     */
    public static function compute(int $successes, int $total, float $z = self::Z_95): array
    {
        if ($total <= 0) {
            return ['low' => null, 'high' => null, 'point' => null];
        }

        $successes = min(max($successes, 0), $total);

        $p = $successes / $total;
        $z2 = $z * $z;
        $denominator = 1 + $z2 / $total;

        $center = ($p + $z2 / (2 * $total)) / $denominator;
        $halfWidth = ($z / $denominator) * sqrt(
            ($p * (1 - $p)) / $total + $z2 / (4 * $total * $total)
        );

        $low = max(0.0, $center - $halfWidth);
        $high = min(1.0, $center + $halfWidth);

        // Snap float drift at the boundaries (e.g. 0.9999999999999999 → 1.0).
        if ($low <= 1e-9) {
            $low = 0.0;
        }
        if ($high >= 1.0 - 1e-9) {
            $high = 1.0;
        }

        return [
            'low' => $low,
            'high' => $high,
            'point' => $p,
        ];
    }

    /**
     * The trend rule (build plan §3.4): a change is real only when the two
     * periods' ranges do not overlap. Returns null when either sample is empty.
     *
     * @param  array{low: float|null, high: float|null}  $before
     * @param  array{low: float|null, high: float|null}  $after
     */
    public static function rangesSeparate(array $before, array $after): ?bool
    {
        if ($before['low'] === null || $after['low'] === null) {
            return null;
        }

        // No overlap: one range lies entirely above or below the other.
        return $after['low'] > $before['high'] || $before['low'] > $after['high'];
    }
}
