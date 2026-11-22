<?php

namespace App\Modules\Collection\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Incremental daily aggregation per (brand, engine, date). Upserted by the
 * pipeline as answers land, so partial sweeps stay consistent.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property string $engine
 * @property Carbon $date
 * @property int $answers
 * @property int $failed
 * @property int $mentions_you
 * @property int $citations_you
 * @property int $named_first
 * @property int $position_sum
 * @property int $positive
 * @property int $neutral
 * @property int $negative
 */
class AnswerDailyStat extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'brand_id', 'engine', 'date',
        'answers', 'failed', 'mentions_you', 'citations_you',
        'named_first', 'position_sum', 'positive', 'neutral', 'negative',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
