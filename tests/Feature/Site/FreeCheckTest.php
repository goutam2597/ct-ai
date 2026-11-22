<?php

use App\Modules\Site\Models\FreeCheck;
use App\Modules\Site\Services\FreeCheckService;
use Illuminate\Support\Facades\Config;

/**
 * Lead-magnet tests: grading, evidence, rate limits and the daily budget cap
 * (the check costs real provider answers — triple-capped, build plan §6.3).
 */
it('runs a free check and grades the result', function (): void {
    $response = $this->post('/free-ai-visibility-check', [
        'email' => 'lead@buyer.test',
        'brand_name' => 'Northwind',
        'website' => 'northwind.example',
        'offering' => 'CRM',
    ]);

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Site/FreeCheck')
            ->has('result')
            ->where('result.brand', 'Northwind'));

    $check = FreeCheck::query()->where('email', 'lead@buyer.test')->firstOrFail();

    expect($check->grade)->toBeIn(['A', 'B', 'C', 'D', 'F'])
        ->and($check->answers_count)->toBe(3)
        ->and($check->mention_rate)->toBe((int) round($check->named_count / 3 * 100))
        ->and((float) $check->cost_usd)->toBeGreaterThan(0);
});

it('gives F to an unknown brand and A to a dominant one', function (): void {
    $service = app(FreeCheckService::class);

    expect($service->grade(75))->toBe('A')
        ->and($service->grade(50))->toBe('B')
        ->and($service->grade(35))->toBe('C')
        ->and($service->grade(20))->toBe('D')
        ->and($service->grade(5))->toBe('F')
        ->and($service->grade(0))->toBe('F');
});

it('limits checks per email per day', function (): void {
    Config::set('clot-site.free_check_max_per_email', 2);

    for ($i = 0; $i < 2; $i++) {
        $this->post('/free-ai-visibility-check', [
            'email' => 'capped@buyer.test',
            'brand_name' => 'Northwind',
            'website' => 'northwind.example',
            'offering' => 'CRM',
        ])->assertOk();
    }

    $this->post('/free-ai-visibility-check', [
        'email' => 'capped@buyer.test',
        'brand_name' => 'Northwind',
        'website' => 'northwind.example',
        'offering' => 'CRM',
    ])->assertSessionHasErrors('free_check');

    expect(FreeCheck::query()->where('email', 'capped@buyer.test')->count())->toBe(2);
});

it('stops at the global daily capacity', function (): void {
    Config::set('clot-site.free_check_max_per_day', 1);

    $this->post('/free-ai-visibility-check', [
        'email' => 'first@buyer.test',
        'brand_name' => 'Northwind',
        'website' => 'northwind.example',
        'offering' => 'CRM',
    ])->assertOk();

    $this->from('/free-ai-visibility-check')->post('/free-ai-visibility-check', [
        'email' => 'second@buyer.test',
        'brand_name' => 'Otherco',
        'website' => 'otherco.example',
        'offering' => 'CRM',
    ])->assertSessionHasErrors('free_check');

    expect(FreeCheck::query()->count())->toBe(1);
});
