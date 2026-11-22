<?php

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property array<string, mixed> $feature_flags
 * @property array<string, array<string, float|int>> $prices
 * @property bool $is_default
 * @property int $sort_order
 */
class Plan extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'feature_flags', 'prices', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'feature_flags' => 'array',
            'prices' => 'array',
            'is_default' => 'boolean',
        ];
    }

    /** @return array<string, mixed> */
    public static function defaultFlags(): array
    {
        return [
            'answers_per_month' => 150,
            'brands' => 1,
            'seats' => 3,
            'prompts' => 10,
            'engines' => ['chatgpt', 'gemini', 'google-ai-overviews'],
            'api_access' => false,
            'white_label' => false,
            'byok' => false,
            'daily_frequency' => false,
            'app_checks' => false,
            'sso' => false,
        ];
    }

    /** Monthly price in USD (yearly prices live in prices['USD']['year']). */
    public function monthlyPriceUsd(): float
    {
        return (float) ($this->prices['USD']['month'] ?? 0);
    }

    public static function defaultPlan(): ?self
    {
        /** @var Plan|null */
        return self::query()->withoutGlobalScopes()->where('is_default', true)->first();
    }
}
