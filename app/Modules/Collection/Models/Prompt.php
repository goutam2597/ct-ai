<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\PromptStatus;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property string $text
 * @property string|null $topic
 * @property string|null $stage
 * @property string|null $persona
 * @property PromptStatus $status
 * @property bool $is_suggested
 */
class Prompt extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'brand_id', 'text', 'topic', 'stage', 'persona', 'status', 'is_suggested',
    ];

    protected function casts(): array
    {
        return [
            'status' => PromptStatus::class,
            'is_suggested' => 'boolean',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
