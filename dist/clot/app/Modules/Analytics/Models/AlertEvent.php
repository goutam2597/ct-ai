<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One sent alert. The dedup_key (rule + brand + period) carries the
 * "each alert is sent once" guarantee at the database level.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int|null $brand_id
 * @property string $rule
 * @property string $dedup_key
 * @property string $title
 * @property array<string, mixed>|null $body
 * @property Carbon|null $created_at
 */
class AlertEvent extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'brand_id', 'rule', 'dedup_key', 'title', 'body'];

    protected function casts(): array
    {
        return [
            'body' => 'array',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
