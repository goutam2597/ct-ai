<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\CellStatus;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $sweep_id
 * @property int $prompt_id
 * @property int $brand_market_id
 * @property string $engine
 * @property int $run_no
 * @property string $idempotency_key
 * @property CellStatus $status
 * @property string|null $failure_reason
 */
class SweepCell extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'sweep_id', 'prompt_id', 'brand_market_id',
        'engine', 'run_no', 'idempotency_key', 'status', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => CellStatus::class,
        ];
    }

    /** @return BelongsTo<Sweep, $this> */
    public function sweep(): BelongsTo
    {
        return $this->belongsTo(Sweep::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** @return BelongsTo<BrandMarket, $this> */
    public function market(): BelongsTo
    {
        return $this->belongsTo(BrandMarket::class, 'brand_market_id');
    }

    /** @return BelongsTo<Answer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class, 'sweep_cell_id');
    }
}
