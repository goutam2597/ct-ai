<?php

use App\Models\User;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Database\Factories\Modules\Collection\Models\BrandFactory;
use Database\Factories\Modules\Collection\Models\CompetitorFactory;
use Database\Factories\Modules\Collection\Models\PromptFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

/** Create a workspace + owner membership and return both. */
function makeWorkspaceWithOwner(string $name = 'Acme'): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id, 'name' => $name]);
    Membership::factory()->owner()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $owner->id,
        'role' => WorkspaceRole::Owner,
    ]);

    return [$owner, $workspace];
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
