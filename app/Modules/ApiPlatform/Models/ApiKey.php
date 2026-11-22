<?php

namespace App\Modules\ApiPlatform\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A workspace API key. The key acts as the person who created it, with that
 * member's permissions (build plan §6.1 API row).
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $created_by
 * @property string $name
 * @property string $token_hash
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property-read User $creator
 */
class ApiKey extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'created_by', 'name', 'token_hash', 'last_used_at'];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function hashToken(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public static function tokenPrefix(): string
    {
        return 'vg_';
    }
}
