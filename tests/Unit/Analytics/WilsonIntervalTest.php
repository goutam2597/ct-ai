<?php

use App\Modules\Analytics\Services\WilsonInterval;

/**
 * Golden tests: the numbers CLOT shows customers must be provably correct.
 * Expected values computed independently with the Wilson formula at
 * z = 1.959963985 (verified against published interval tables).
 */
it('computes the published Wilson bounds for 50 of 100', function (): void {
    $r = WilsonInterval::compute(50, 100);

    expect($r['point'])->toBe(0.5)
        ->and($r['low'])->toEqualWithDelta(0.4039, 0.002)
        ->and($r['high'])->toEqualWithDelta(0.5962, 0.002);
});

it('computes the published Wilson bounds for 1 of 10', function (): void {
    $r = WilsonInterval::compute(1, 10);

    expect($r['point'])->toBe(0.1)
        ->and($r['low'])->toEqualWithDelta(0.0179, 0.002)
        ->and($r['high'])->toEqualWithDelta(0.4042, 0.002);
});

it('clamps near-zero and near-certain rates', function (): void {
    $zero = WilsonInterval::compute(0, 10);
    $one = WilsonInterval::compute(10, 10);

    expect($zero['low'])->toBe(0.0)
        ->and($zero['high'])->toEqualWithDelta(0.2775, 0.002)
        ->and($one['low'])->toEqualWithDelta(0.7224, 0.002)
        ->and($one['high'])->toBe(1.0);
});

it('returns nulls for an empty sample instead of faking a rate', function (): void {
    $r = WilsonInterval::compute(5, 0);

    expect($r['low'])->toBeNull()
        ->and($r['high'])->toBeNull()
        ->and($r['point'])->toBeNull();
});

it('always contains the point estimate', function (): void {
    foreach ([[0, 10], [1, 10], [3, 10], [5, 30], [54, 60], [97, 100], [100, 100]] as [$s, $n]) {
        $r = WilsonInterval::compute($s, $n);

        expect($r['low'])->toBeLessThanOrEqual($r['point'])
            ->and($r['point'])->toBeLessThanOrEqual($r['high']);
    }
});

it('narrows as the sample grows', function (): void {
    $small = WilsonInterval::compute(5, 10);
    $large = WilsonInterval::compute(50, 100);

    $widthSmall = $small['high'] - $small['low'];
    $widthLarge = $large['high'] - $large['low'];

    expect($widthLarge)->toBeLessThan($widthSmall);
});

it('calls a trend only when the ranges separate', function (): void {
    expect(WilsonInterval::rangesSeparate(
        ['low' => 0.10, 'high' => 0.20],
        ['low' => 0.30, 'high' => 0.50],
    ))->toBeTrue()
        ->and(WilsonInterval::rangesSeparate(
            ['low' => 0.10, 'high' => 0.30],
            ['low' => 0.25, 'high' => 0.50],
        ))->toBeFalse()
        ->and(WilsonInterval::rangesSeparate(
            ['low' => null, 'high' => null],
            ['low' => 0.25, 'high' => 0.50],
        ))->toBeNull();
});
