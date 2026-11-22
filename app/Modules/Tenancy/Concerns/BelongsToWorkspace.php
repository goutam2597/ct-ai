<?php

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Models\Workspace;
use App\Modules\Tenancy\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Workspace isolation for tenant-owned rows.
 *
 * - Reads: a global scope filters every query to the current workspace context.
 *   When no context is set (console, operator cross-workspace views), the scope
 *   is inert — cross-workspace access in those paths must go through services
 *   and is covered by the isolation test suite.
 * - Writes: creating() stamps workspace_id from the context; a model created
 *   with no context and no explicit workspace_id refuses to save rather than
 *   silently landing in the wrong tenant.
 */
trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $currentId = WorkspaceContext::currentId();

            if ($currentId !== null) {
                $builder->where($builder->getModel()->getTable().'.workspace_id', $currentId);
            }
        });

        static::creating(function (Model $model): void {
            if ($model->getAttribute('workspace_id') === null) {
                $model->setAttribute('workspace_id', WorkspaceContext::currentId());
            }

            if ($model->getAttribute('workspace_id') === null) {
                throw new \RuntimeException(
                    static::class.' created without a workspace_id and no workspace context.'
                );
            }
        });
    }

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** Escape hatch for operator/admin cross-workspace queries.
     *
     * @return Builder<static>
     */
    public static function withoutWorkspaceScope(): Builder
    {
        return static::query()->withoutGlobalScope('workspace');
    }
}
