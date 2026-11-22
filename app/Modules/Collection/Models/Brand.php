<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\BrandFrequency;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Database\Factories\Modules\Collection\Models\BrandFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $workspace_id
 * @property string $name
 * @property list<string> $domains
 * @property list<string> $aliases
 * @property list<string> $engines
 * @property int $runs_per_prompt
 * @property BrandFrequency $frequency
 * @property bool $is_active
 * @property Carbon|null $next_sweep_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, BrandMarket> $markets
 * @property-read Collection<int, Competitor> $competitors
 * @property-read Collection<int, Prompt> $prompts
 * @property-read Collection<int, Sweep> $sweeps
 */
class Brand extends Model
{
    use BelongsToWorkspace;

    /** @use HasFactory<BrandFactory> */
    use HasFactory;

    protected $fillable = [
        'workspace_id', 'name', 'domains', 'aliases', 'engines',
        'runs_per_prompt', 'frequency', 'is_active', 'next_sweep_at',
    ];

    protected function casts(): array
    {
        return [
            'domains' => 'array',
            'aliases' => 'array',
            'engines' => 'array',
            'frequency' => BrandFrequency::class,
            'is_active' => 'boolean',
            'next_sweep_at' => 'datetime',
        ];
    }

    /** @return HasMany<BrandMarket, $this> */
    public function markets(): HasMany
    {
        return $this->hasMany(BrandMarket::class);
    }

    /** @return HasMany<Competitor, $this> */
    public function competitors(): HasMany
    {
        return $this->hasMany(Competitor::class);
    }

    /** @return HasMany<Prompt, $this> */
    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    /** @return HasMany<Sweep, $this> */
    public function sweeps(): HasMany
    {
        return $this->hasMany(Sweep::class);
    }

    /**
     * Every name/domain string this brand is recognised by.
     *
     * @return list<string>
     */
    public function identityStrings(): array
    {
        return array_values(array_filter(array_merge(
            [$this->name],
            $this->aliases ?? [],
            $this->domains ?? [],
        )));
    }
}
