<?php

namespace App\Modules\Collection\Enums;

enum BrandFrequency: string
{
    case Weekly = 'weekly';
    case TwiceWeekly = 'twice_weekly';
    case Daily = 'daily';

    /** Interval in days between sweeps. */
    public function intervalDays(): int
    {
        return match ($this) {
            self::Weekly => 7,
            self::TwiceWeekly => 4,
            self::Daily => 1,
        };
    }
}
