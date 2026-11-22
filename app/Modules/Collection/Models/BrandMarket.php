<?php

namespace App\Modules\Collection\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One country+language pair a brand is tracked in. Every brand has at least
 * the primary market; extra markets multiply sweep cells.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property string $country_code
 * @property string $language_code
 * @property bool $is_primary
 */
class BrandMarket extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'brand_id', 'country_code', 'language_code', 'is_primary'];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** "US", "en" style market key used in API params and UI. */
    public function key(): string
    {
        return $this->country_code.'-'.$this->language_code;
    }
}
