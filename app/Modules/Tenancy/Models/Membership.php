<?php

namespace App\Modules\Tenancy\Models;

use App\Models\User;
use App\Modules\Tenancy\Enums\BrandAccess;
use App\Modules\Tenancy\Enums\WorkspaceRole;
use Database\Factories\Modules\Tenancy\Models\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Deliberately NOT workspace-context-scoped: a user's membership rows must
 * stay visible across workspaces so they can switch context and policies can
 * resolve their role anywhere.
 *
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property WorkspaceRole $role
 * @property BrandAccess $brand_access
 * @property Carbon|null $invited_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Membership extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $fillable = ['workspace_id', 'user_id', 'role', 'brand_access', 'invited_at', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'role' => WorkspaceRole::class,
            'brand_access' => BrandAccess::class,
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
