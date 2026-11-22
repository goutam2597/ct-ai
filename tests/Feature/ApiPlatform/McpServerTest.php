<?php

use App\Models\User;
use App\Modules\Collection\Services\Sweep\SweepRunner;
use App\Modules\Tenancy\Models\Membership;
use App\Modules\Tenancy\Models\Workspace;
use Database\Factories\Modules\Collection\Models\BrandFactory;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * MCP server tests: JSON-RPC 2.0 contract (initialize → tools/list →
 * tools/call), same Bearer-key auth as REST, read-only tool surface.
 */
function mcpCall(string $token, array $payload): TestResponse
{
    $response = app(Kernel::class)->handle(
        Request::create('/mcp/visibility', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($payload)),
    );

    return TestResponse::fromBaseResponse($response);
}

it('answers initialize with protocol version and server info', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    $token = makeApiKey($owner, $workspace);

    mcpCall($token, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertOk()
        ->assertJsonPath('result.protocolVersion', '2024-11-05')
        ->assertJsonPath('result.serverInfo.name', 'clot-ai-visibility');
});

it('lists exactly the seven read-only tools', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    $token = makeApiKey($owner, $workspace);

    $response = mcpCall($token, ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list']);

    $response->assertOk();

    $names = collect($response->json('result.tools'))->pluck('name')->all();

    expect($names)->toBe([
        'list_brands', 'brand_overview', 'prompt_performance', 'competitor_comparison',
        'top_sources', 'next_steps', 'search_answers',
    ]);
});

it('rejects unknown methods with a JSON-RPC error', function (): void {
    [$owner, $workspace] = makeWorkspaceWithOwner();
    $token = makeApiKey($owner, $workspace);

    mcpCall($token, ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'resources/read'])
        ->assertOk()
        ->assertJsonPath('error.code', -32601);
});

it('calls brand_overview with ranges and is read-only safe on bad brand', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);
    $token = makeApiKey($owner, $workspace);

    mcpCall($token, ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => [
        'name' => 'brand_overview',
        'arguments' => ['brand_id' => $brand->id, 'days' => 30],
    ]])
        ->assertOk()
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.content.0.type', 'text');

    // Foreign brand → tool error (never leaked).
    $other = Workspace::factory()->create();
    $otherOwner = User::factory()->create();
    Membership::factory()->owner()->create([
        'workspace_id' => $other->id, 'user_id' => $otherOwner->id,
    ]);
    $otherBrand = BrandFactory::new()->forWorkspace($other)->create();

    $foreign = mcpCall($token, ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => [
        'name' => 'brand_overview',
        'arguments' => ['brand_id' => $otherBrand->id],
    ]]);

    expect($foreign->json('result.isError'))->toBeTrue()
        ->and($foreign->json('result.content.0.text'))->toContain('not found');
});

it('searches stored answers over MCP', function (): void {
    [$owner, $workspace, $brand] = makeTrackingBrand(runsPerPrompt: 3);
    app(SweepRunner::class)->runForBrand($brand);
    $token = makeApiKey($owner, $workspace);

    $response = mcpCall($token, ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'tools/call', 'params' => [
        'name' => 'search_answers',
        'arguments' => ['brand_id' => $brand->id, 'query' => 'CRM'],
    ]]);

    $response->assertOk()->assertJsonPath('result.isError', false);
});

it('requires valid key auth like the REST API', function (): void {
    mcpCall('vg_wrong_key', ['jsonrpc' => '2.0', 'id' => 7, 'method' => 'tools/list'])
        ->assertStatus(401);
});
