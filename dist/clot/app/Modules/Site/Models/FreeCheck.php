<?php

namespace App\Modules\Site\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One run of the public lead magnet. A global table (no workspace): free
 * checks happen before anyone signs up.
 *
 * @property int $id
 * @property string $email
 * @property string $brand_name
 * @property string $website
 * @property string $offering
 * @property string $grade
 * @property int $mention_rate
 * @property int $answers_count
 * @property int $named_count
 * @property string $cost_usd
 * @property string $ip_hash
 * @property Carbon|null $created_at
 */
final class FreeCheck extends Model
{
    protected $fillable = [
        'email', 'brand_name', 'website', 'offering',
        'grade', 'mention_rate', 'answers_count', 'named_count', 'cost_usd', 'ip_hash',
    ];

    protected function casts(): array
    {
        return [
            'cost_usd' => 'decimal:4',
        ];
    }
}
