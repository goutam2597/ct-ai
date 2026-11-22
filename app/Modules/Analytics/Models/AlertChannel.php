<?php

namespace App\Modules\Analytics\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Where alerts go besides email and the bell: a signed webhook or a Slack
 * incoming-webhook URL, owned by the workspace (owner/admin manage).
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $type
 * @property string $target
 * @property string|null $secret
 * @property bool $is_active
 */
class AlertChannel extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'type', 'target', 'secret', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
