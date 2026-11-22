<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\SweepStatus;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property SweepStatus $status
 * @property int $cells_total
 * @property int $cells_collected
 * @property int $cells_failed
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class Sweep extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'brand_id', 'status',
        'cells_total', 'cells_collected', 'cells_failed', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SweepStatus::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return HasMany<SweepCell, $this> */
    public function cells(): HasMany
    {
        return $this->hasMany(SweepCell::class);
    }

    /** @return HasMany<Answer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }
}
