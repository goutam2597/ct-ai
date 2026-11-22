<?php

namespace App\Modules\Collection\Enums;

enum CellStatus: string
{
    case Pending = 'pending';
    case Collected = 'collected';
    case Failed = 'failed';
}
