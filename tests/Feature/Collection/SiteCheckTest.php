<?php

use App\Modules\Collection\Services\Site\SiteCheckService;
use Illuminate\Support\Facades\Http;

/**
 * Site check tests: robots.txt parsing, llms.txt generation, score
 * computation — all offline (Http::fake for the brand's own domain).
 */
it('scores a site with everything correct at 100', function (): void {
    Http::fake([
        'https://example.test/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://example.test/sitemap.xml"),
        'https://example.test/llms.txt' => Http::response('# Example\n> A brand.'),
        'https://example.test' => Http::response(
            '<html><head><title>Example</title><meta name="description" content="A brand">'
            .'</head><body>'
            .'<script type="application/ld+json">{"@type":"Organization"}</script>'
            .'<script type="application/ld+json">{"@type":"Product"}</script>'
            .str_repeat('<p>Real content here.</p>', 30)
            .'</body></html>'
        ),
    ]);

    $check = app(SiteCheckService::class)->check('example.test');

    expect($check['score'])->toBe(100)
        ->and($check['checks']['answer_crawlers_allowed']['pass'])->toBeTrue()
        ->and($check['checks']['llms_txt']['pass'])->toBeTrue()
        ->and($check['checks']['organisation_markup']['pass'])->toBeTrue();
});

it('blocks the answer crawler and loses points', function (): void {
    Http::fake([
        'https://example.test/robots.txt' => Http::response("User-agent: PerplexityBot\nDisallow: /\nUser-agent: *\nAllow: /"),
        'https://example.test/llms.txt' => Http::response('not found', 404),
        'https://example.test' => Http::response('<html><head><title>T</title></head><body><p>Content</p></body></html>'),
    ]);

    $check = app(SiteCheckService::class)->check('example.test');

    expect($check['score'])->toBeLessThan(100)
        ->and($check['checks']['answer_crawlers_allowed']['pass'])->toBeFalse()
        ->and($check['crawlers']['answer'][4]['bot'])->toBe('PerplexityBot')
        ->and($check['crawlers']['answer'][4]['blocked'])->toBeTrue();
});

it('distinguishes answer crawlers from training crawlers', function (): void {
    Http::fake([
        'https://example.test/robots.txt' => Http::response("User-agent: GPTBot\nDisallow: /\nUser-agent: *\nAllow: /"),
        'https://example.test/llms.txt' => Http::response('not found', 404),
        'https://example.test' => Http::response('<html><head><title>T</title></head><body><p>X</p></body></html>'),
    ]);

    $check = app(SiteCheckService::class)->check('example.test');

    // GPTBot is a training crawler — blocking it is a choice, not a mistake.
    $training = collect($check['crawlers']['training'])->firstWhere('bot', 'GPTBot');
    $answer = collect($check['crawlers']['answer'])->firstWhere('bot', 'OAI-SearchBot');

    expect($training['blocked'])->toBeTrue()
        ->and($answer['blocked'])->toBeFalse();
});

it('generates an llms.txt draft when the site has none', function (): void {
    Http::fake([
        'https://newbrand.test/robots.txt' => Http::response("User-agent: *\nAllow: /"),
        'https://newbrand.test/llms.txt' => Http::response('not found', 404),
        'https://newbrand.test' => Http::response('<html><head><title>NewBrand</title></head><body><p>Hello</p></body></html>'),
    ]);

    $check = app(SiteCheckService::class)->check('newbrand.test');

    expect($check['checks']['llms_txt']['pass'])->toBeFalse()
        ->and($check['llmsTxt'])->toContain('# NewBrand')
        ->and($check['llmsTxt'])->toContain('newbrand.test');
});

it('strips www from domains', function (): void {
    $service = app(SiteCheckService::class);

    expect((new ReflectionClass($service))->getMethod('normaliseDomain')->invoke($service, 'https://www.example.test/page'))
        ->toBe('example.test');
});
