<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\MatchedBy;
use App\Modules\Collection\Enums\Tone;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A brand (the tracked brand or one of its competitors) named inside an answer.
 * Your-mention rows point at the tracked brand; rival rows carry the
 * competitor's own id — rival-pull-ahead alerts need identity.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $answer_id
 * @property int|null $brand_id
 * @property int|null $competitor_id
 * @property int|null $position
 * @property Tone|null $tone
 * @property MatchedBy $matched_by
 * @property bool $is_you
 */
class AnswerMention extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'answer_id', 'brand_id', 'competitor_id', 'position', 'tone', 'matched_by', 'is_you',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'tone' => Tone::class,
            'matched_by' => MatchedBy::class,
            'is_you' => 'boolean',
        ];
    }

    /** @return BelongsTo<Answer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
