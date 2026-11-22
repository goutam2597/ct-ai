<?php

namespace App\Modules\Collection\Models;

use App\Modules\Tenancy\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only allowance ledger (ADR-0004). Negative delta = claim made before
 * a provider call; positive delta = refund for a failed collection. The
 * (workspace_id, period, claim_key) unique index makes double-claims
 * impossible at the database level.
 *
 * @property int $id
 * @property int $workspace_id
 * @property string $period
 * @property int $delta
 * @property string|null $claim_key
 * @property string $reason
 */
class AllowanceLedger extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'period', 'delta', 'claim_key', 'reason'];
}
