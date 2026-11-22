<?php

use App\Mail\WeeklyDigestMail;
use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Models\Answer;
use App\Modules\Collection\Models\Prompt;
use App\Modules\Collection\Models\Sweep;
use App\Modules\Collection\Models\SweepCell;
use Illuminate\Support\Facades\Mail;

/**
 * The Monday digest: only workspaces with data, honest trends, sent to the
 * owner. Tested with Mail::fake — no live mail in CI.
 */
function seedDigestAnswer($brand, $createdAt, bool $named, ?int $competitorId = null): void
{
    static $seed = 0;
    $seed++;

    $prompt = Prompt::query()->where('brand_id', $brand->id)->firstOrFail();
    $market = $brand->markets()->firstOrFail();
    $sweep = Sweep::withoutWorkspaceScope()->where('brand_id', $brand->id)->first();
    if ($sweep === null) {
        $sweep = Sweep::withoutWorkspaceScope()->create([
            'workspace_id' => $brand->workspace_id, 'brand_id' => $brand->id, 'status' => 'completed',
        ]);
    }
    $cell = SweepCell::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'sweep_id' => $sweep->id, 'prompt_id' => $prompt->id,
        'brand_market_id' => $market->id, 'engine' => 'chatgpt', 'run_no' => 1,
        'idempotency_key' => 'digest-'.$seed, 'status' => 'collected',
    ]);
    $answer = Answer::withoutWorkspaceScope()->create([
        'workspace_id' => $brand->workspace_id, 'sweep_cell_id' => $cell->id, 'brand_id' => $brand->id,
        'prompt_id' => $prompt->id, 'engine' => 'chatgpt', 'method' => 'demo',
        'status' => AnswerStatus::Collected, 'answer_text' => 'x', 'named_you' => $named, 'cited_you' => false,
    ]);
    $answer->forceFill(['created_at' => $createdAt])->saveQuietly();
}

it('sends the weekly digest with honest trends to workspace owners', function (): void {
    Mail::fake();

    [$owner, , $brand, $competitors] = makeTrackingBrand(name: 'Digestco', competitorNames: ['Contoso']);

    // Last week: 30 answers, 21 named (70%). Rival named in 9.
    for ($i = 0; $i < 30; $i++) {
        seedDigestAnswer($brand, now()->subDays(3)->subMinutes($i), $i < 21);
    }

    $this->artisan('clot:digest-send')->assertSuccessful();

    Mail::assertQueued(WeeklyDigestMail::class, 1);

    Mail::assertQueued(WeeklyDigestMail::class, function (WeeklyDigestMail $mail) use ($owner, $brand) {
        return $mail->hasTo($owner->email)
            && $mail->rows[0]['name'] === $brand->name
            && $mail->rows[0]['trend'] === '—' // no prior week: no trend call
            && $mail->rows[0]['answers'] === 30;
    });
});

it('skips workspaces with no answers this week', function (): void {
    Mail::fake();

    makeWorkspaceWithOwner('Empty Inc');

    $this->artisan('clot:digest-send')->assertSuccessful();

    Mail::assertNothingSent();
});
