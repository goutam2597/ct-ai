<?php

namespace Database\Factories\Modules\Collection\Models;

use App\Modules\Collection\Enums\BrandFrequency;
use App\Modules\Collection\Models\Brand;
use App\Modules\Tenancy\Models\Workspace;
use Database\Factories\Modules\Tenancy\Models\WorkspaceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Brand> */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        $domain = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name)).'.example';

        return [
            'workspace_id' => WorkspaceFactory::new(),
            'name' => $name,
            'domains' => [$domain],
            'aliases' => [],
            'engines' => ['chatgpt', 'claude'],
            'runs_per_prompt' => 3,
            'frequency' => BrandFrequency::Weekly,
            'is_active' => true,
            'next_sweep_at' => null,
        ];
    }

    /** Create the brand together with its primary market row. */
    public function withPrimaryMarket(string $country = 'US', string $language = 'en'): self
    {
        return $this->afterCreating(function (Brand $brand) use ($country, $language): void {
            $brand->markets()->create([
                'workspace_id' => $brand->workspace_id,
                'country_code' => $country,
                'language_code' => $language,
                'is_primary' => true,
            ]);
        });
    }

    public function forWorkspace(Workspace $workspace): self
    {
        return $this->state(fn (): array => ['workspace_id' => $workspace->id]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
