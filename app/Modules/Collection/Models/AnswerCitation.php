<?php

namespace App\Modules\Collection\Models;

use App\Modules\Collection\Enums\OwnerType;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $answer_id
 * @property string $url
 * @property string $domain
 * @property OwnerType $owner_type
 */
class AnswerCitation extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'answer_id', 'url', 'domain', 'owner_type'];

    protected function casts(): array
    {
        return [
            'owner_type' => OwnerType::class,
        ];
    }

    /** @return BelongsTo<Answer, $this> */
    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }
}
