<?php

namespace App\Modules\Collection\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property string $name
 * @property list<string> $domains
 * @property list<string> $aliases
 */
class Competitor extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'brand_id', 'name', 'domains', 'aliases'];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'aliases' => 'array',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return list<string> */
    public function identityStrings(): array
    {
        return array_values(array_filter(array_merge(
            [$this->name],
            $this->aliases ?? [],
            $this->domains ?? [],
        )));
    }
}
