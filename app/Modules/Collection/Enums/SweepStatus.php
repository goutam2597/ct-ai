<?php

namespace App\Modules\Collection\Enums;

enum SweepStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
}
