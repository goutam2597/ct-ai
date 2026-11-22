<?php

namespace Database\Factories\Modules\Collection\Models;

use App\Modules\Collection\Enums\PromptStatus;
use App\Modules\Collection\Models\Brand;
use App\Modules\Collection\Models\Prompt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Prompt> */
class PromptFactory extends Factory
{
    protected $model = Prompt::class;

    public function definition(): array
    {
        return [
            'brand_id' => BrandFactory::new(),
            'text' => $this->faker->unique()->sentence().'?',
            'topic' => 'general',
            'stage' => 'consideration',
            'persona' => null,
            'status' => PromptStatus::Active,
            'is_suggested' => false,
        ];
    }

    public function forBrand(Brand $brand): self
    {
        return $this->state(fn (): array => [
            'brand_id' => $brand->id,
            'workspace_id' => $brand->workspace_id,
        ]);
    }

    public function paused(): self
    {
        return $this->state(fn (): array => ['status' => PromptStatus::Paused]);
    }
}
