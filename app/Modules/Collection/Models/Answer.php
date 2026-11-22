<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\AnswerStatus;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One stored response. Failed collections keep their reason and are excluded
 * from every rate (named_you/cited_you/position/tone stay null).
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $sweep_cell_id
 * @property int $brand_id
 * @property int $prompt_id
 * @property string $engine
 * @property string $method
 * @property string|null $model
 * @property AnswerStatus $status
 * @property string|null $answer_text
 * @property string|null $failure_reason
 * @property bool|null $named_you
 * @property bool|null $cited_you
 * @property int|null $position
 * @property Tone|null $tone
 * @property string|null $provider_cost_usd
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property list<string>|null $searches_run
 * @property list<string>|null $cited_links
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Answer extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'sweep_cell_id', 'brand_id', 'prompt_id', 'engine', 'method',
        'model', 'status', 'answer_text', 'failure_reason',
        'named_you', 'cited_you', 'position', 'tone',
        'provider_cost_usd', 'input_tokens', 'output_tokens',
        'searches_run', 'cited_links',
    ];

    protected function casts(): array
    {
        return [
            'status' => AnswerStatus::class,
            'named_you' => 'boolean',
            'cited_you' => 'boolean',
            'position' => 'integer',
            'tone' => Tone::class,
            'provider_cost_usd' => 'decimal:4',
            'searches_run' => 'array',
            'cited_links' => 'array',
        ];
    }

    /** @return BelongsTo<SweepCell, $this> */
    public function cell(): BelongsTo
    {
        return $this->belongsTo(SweepCell::class, 'sweep_cell_id');
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Prompt, $this> */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /** @return HasMany<AnswerMention, $this> */
    public function mentions(): HasMany
    {
        return $this->hasMany(AnswerMention::class);
    }

    /** @return HasMany<AnswerCitation, $this> */
    public function citations(): HasMany
    {
        return $this->hasMany(AnswerCitation::class);
    }
}
