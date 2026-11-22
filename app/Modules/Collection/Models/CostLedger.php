<?php

namespace App\Modules\Collection\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only cost ledger (ADR-0004): the real provider cost of every stored
 * answer, powering per-engine and per-workspace spend reporting and the
 * install-wide daily spend cap.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $answer_id
 * @property string $engine
 * @property string $provider_cost_usd
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 */
class CostLedger extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'answer_id', 'engine', 'provider_cost_usd', 'input_tokens', 'output_tokens',
    ];

    protected function casts(): array
    {
        return [
            'provider_cost_usd' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<Answer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
