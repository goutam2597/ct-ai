<?php

use App\Modules\Collection\Collectors\FakeCollector;
use App\Modules\Collection\DTO\CollectRequest;
use App\Modules\Collection\Services\CollectorManager;

it('resolves a collector for every registered engine', function (): void {
    $manager = app(CollectorManager::class);

    foreach ($manager->engineKeys() as $engine) {
        expect($manager->has($engine))->toBeTrue()
            ->and($manager->for($engine))->toBeObject();
    }
});

it('ships the fake collector as the default for all nine engines', function (): void {
    $manager = app(CollectorManager::class);

    expect($manager->engineKeys())->toBe([
        'chatgpt', 'gemini', 'google-ai-overviews', 'google-ai-mode', 'claude',
        'perplexity', 'grok', 'deepseek', 'le-chat',
    ]);
});

it('returns a failed response instead of throwing for unknown engines', function (): void {
    $manager = app(CollectorManager::class);

    $response = $manager->collect(new CollectRequest(
        prompt: 'What is the best CRM?',
        countryCode: 'US',
        languageCode: 'en',
        engine: 'not-an-engine',
        method: 'api',
    ));

    expect($response->isCollected())->toBeFalse()
        ->and($response->failureReason)->toContain('not-an-engine');
});

it('is deterministic per idempotency key (safe retries, no double answers)', function (): void {
    $manager = app(CollectorManager::class);

    $request = fn () => new CollectRequest(
        prompt: 'What is the best CRM for a small agency?',
        countryCode: 'US',
        languageCode: 'en',
        engine: 'chatgpt',
        method: 'demo',
        idempotencyKey: 'cell-42',
    );

    $first = $manager->collect($request());
    $second = $manager->collect($request());

    expect($first->isCollected())->toBeTrue()
        ->and($second->answerText)->toBe($first->answerText)
        ->and($first->searchesRun)->toBe($second->searchesRun);
});

it('reports simulated failures for prompts listed in fail_on', function (): void {
    $collector = new FakeCollector(
        failOn: ['email automation']
    );

    $response = $collector->collect(new CollectRequest(
        prompt: 'Best CRM with email automation?',
        countryCode: 'US',
        languageCode: 'en',
        engine: 'chatgpt',
        method: 'demo',
    ));

    expect($response->isCollected())->toBeFalse()
        ->and($response->failureReason)->toContain('simulated failure');
});

it('names a tracked brand back when the prompt mentions it', function (): void {
    $collector = new FakeCollector;

    $response = $collector->collect(new CollectRequest(
        prompt: 'Northwind or Rivalsoft for a 10-person team?',
        countryCode: 'US',
        languageCode: 'en',
        engine: 'claude',
        method: 'demo',
    ));

    expect($response->isCollected())->toBeTrue()
        ->and($response->answerText)->toContain('Northwind');
});
