<?php

use App\Modules\Collection\DTO\CollectorResponse;
use App\Modules\Collection\Enums\OwnerType;
use App\Modules\Collection\Services\Analysis\CitationScan;
use App\Modules\Collection\Services\Analysis\MentionScan;

function brandFixture(): array
{
    [$owner, $workspace, $brand, $competitors] = makeTrackingBrand(
        name: 'Northwind',
        competitorNames: ['Contoso'],
    );

    return [$brand, $competitors[0]];
}

it('finds the brand and competitors in order with tone', function (): void {
    [$brand, $contoso] = brandFixture();
    $scan = new MentionScan;

    $response = CollectorResponse::collected(
        'For a small agency, Northwind is the easiest pick with a great client portal. Contoso suits larger teams, though it is pricey and slow to set up.'
    );

    $result = $scan->scan($brand, $response->answerText, $response, [$contoso]);

    expect($result->namedYou)->toBeTrue()
        ->and($result->yourPosition)->toBe(1)
        ->and($result->yourTone->value)->toBe('positive')
        ->and(count($result->mentioned))->toBe(2)
        ->and($result->mentioned[1]['brand']->id)->toBe($contoso->id)
        ->and($result->mentioned[1]['position'])->toBe(2)
        ->and($result->mentioned[1]['tone']->value)->toBe('negative');
});

it('matches aliases as the brand', function (): void {
    [$brand, $contoso] = brandFixture();
    $brand->forceFill(['aliases' => ['NW']])->save();

    $response = CollectorResponse::collected('NW is worth a look for small teams.');
    $result = (new MentionScan)->scan($brand, $response->answerText, $response, [$contoso]);

    expect($result->namedYou)->toBeTrue()
        ->and($result->mentioned[0]['matched_by']->value)->toBe('alias');
});

it('counts a cited own domain towards the citation rate', function (): void {
    [$brand, $contoso] = brandFixture();
    $scan = new MentionScan;

    $response = CollectorResponse::collected(
        'Look at Northwind for pipelines.',
        citedLinks: ['https://www.northwind.example/pricing?utm_source=chatgpt.com']
    );

    $result = $scan->scan($brand, $response->answerText, $response, [$contoso]);

    expect($result->citedYou)->toBeTrue();
});

it('classifies citations as yours, competitor or third party', function (): void {
    [$brand, $contoso] = brandFixture();
    $scan = new CitationScan;

    $citations = $scan->classifyAll([
        'https://www.northwind.example/pricing?utm_source=chatgpt.com',
        'https://contoso.example/plans',
        'https://g2.com/categories/crm',
        'not a link at all',
    ], $brand, [$contoso]);

    expect(count($citations))->toBe(3)
        ->and($citations[0]['owner_type'])->toBe(OwnerType::You)
        ->and($citations[0]['domain'])->toBe('northwind.example')
        ->and($citations[1]['owner_type'])->toBe(OwnerType::Competitor)
        ->and($citations[1]['competitor_id'])->toBe($contoso->id)
        ->and($citations[2]['owner_type'])->toBe(OwnerType::ThirdParty);
});

it('never misclassifies a subdomain string as a different domain', function (): void {
    [$brand, $contoso] = brandFixture();
    $scan = new CitationScan;

    $citations = $scan->classifyAll([
        'https://docs.northwind.example/getting-started',
        'https://notnorthwind.example',
    ], $brand, [$contoso]);

    expect($citations[0]['owner_type'])->toBe(OwnerType::You)
        ->and($citations[1]['owner_type'])->toBe(OwnerType::ThirdParty);
});
