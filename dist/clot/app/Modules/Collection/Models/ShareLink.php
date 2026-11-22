<?php

namespace App\Modules\Collection\Models;

use App\Models\User;
use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A read-only client report link. The token is the credential: no login,
 * revocable, never indexed.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $brand_id
 * @property string $token
 * @property int $created_by
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 */
class ShareLink extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'brand_id', 'token', 'created_by', 'revoked_at'];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
