<?php

namespace Database\Factories\Modules\Collection\Models;

use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Competitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Competitor> */
class CompetitorFactory extends Factory
{
    protected $model = Competitor::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();
        $domain = strtolower((string) preg_replace('/[^a-z0-9]/i', '', $name)).'.example';

        return [
            'brand_id' => BrandFactory::new(),
            'name' => $name,
            'domains' => [$domain],
            'aliases' => [],
        ];
    }

    public function forBrand(Brand $brand): self
    {
        return $this->state(fn (): array => [
            'brand_id' => $brand->id,
            'workspace_id' => $brand->workspace_id,
        ]);
    }
}
