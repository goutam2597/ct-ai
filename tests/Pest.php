<?php

use App\Models\User;
use App\Modules\ApiPlatform\Models\ApiKey;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\BillingService;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Database\Factories\Modules\Collection\Models\BrandFactory;
use Database\Factories\Modules\Collection\Models\CompetitorFactory;
use Database\Factories\Modules\Collection\Models\PromptFactory;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * Set the request-level workspace context to a membership the user actually
 * holds. Mirrors what SetWorkspaceContext middleware does after authorization.
 */
function actAsWorkspaceMember(User $user, Workspace $workspace): Membership
{
    $membership = Membership::query()
        ->where('user_id', $user->id)
        ->where('workspace_id', $workspace->id)
        ->firstOrFail();

    app(WorkspaceContext::class)->force($workspace, $membership);

    return $membership;
}

/** Create a workspace via the sanctioned path (owner + plan) and return both. */
function makeWorkspaceWithOwner(string $name = 'Acme'): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::createWithOwner($owner->id, $name);

    return [$owner, $workspace];
}

/** Create or update a test plan and activate it on a workspace. */
function activatePlanWithAllowance(Workspace $workspace, int $answersPerMonth, ?array $engines = null): Plan
{
    $plan = Plan::query()->updateOrCreate(
        ['slug' => 'test-plan-'.$answersPerMonth],
        [
            'name' => 'Test plan ('.$answersPerMonth.')',
            'feature_flags' => array_merge(
                Plan::defaultFlags(),
                ['answers_per_month' => $answersPerMonth],
                $engines !== null ? ['engines' => $engines] : [],
            ),
            'prices' => ['USD' => ['month' => 0, 'year' => 0]],
            'is_default' => false,
        ],
    );

    app(BillingService::class)->activate($workspace, $plan, 'fake');

    return $plan;
}

/**
 * Create a fully seeded tracked brand: workspace + owner, brand with primary
 * market, prompts, and competitors. Returns everything the pipeline tests need.
 */
function makeTrackingBrand(
    string $name = 'Northwind',
    array $promptTexts = [
        'What is the best CRM for a small agency?',
        'Best CRM with email automation?',
    ],
    array $engines = ['chatgpt', 'claude'],
    int $runsPerPrompt = 3,
    ?array $competitorNames = ['Contoso'],
): array {
    [$owner, $workspace] = makeWorkspaceWithOwner('Acme');

    $brand = BrandFactory::new()
        ->forWorkspace($workspace)
        ->withPrimaryMarket()
        ->create([
            'name' => $name,
            'domains' => [strtolower($name).'.example'],
            'engines' => $engines,
            'runs_per_prompt' => $runsPerPrompt,
        ]);

    foreach ($promptTexts as $text) {
        PromptFactory::new()->forBrand($brand)->create(['text' => $text]);
    }

    $competitors = [];

    foreach ((array) $competitorNames as $competitorName) {
        $competitors[] = CompetitorFactory::new()->forBrand($brand)->create([
            'name' => $competitorName,
            'domains' => [strtolower($competitorName).'.example'],
        ]);
    }

    return [$owner, $workspace, $brand, $competitors];
}

// ---- API test helpers (shared between ApiV1Test, McpServerTest, StripeGatewayTest) ----

function makeApiKey(User $user, Workspace $workspace): string
{
    $plaintext = 'vg_'.Str::random(40);

    ApiKey::query()->create([
        'workspace_id' => $workspace->id,
        'created_by' => $user->id,
        'name' => 'Test key',
        'token_hash' => ApiKey::hashToken($plaintext),
    ]);

    return $plaintext;
}

function apiGet(string $token, string $url): TestResponse
{
    $response = app(Kernel::class)->handle(
        Request::create($url, 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
        ]),
    );

    return TestResponse::fromBaseResponse($response);
}

function apiPost(string $token, string $url, array $body): TestResponse
{
    $response = app(Kernel::class)->handle(
        Request::create($url, 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($body)),
    );

    return TestResponse::fromBaseResponse($response);
}
