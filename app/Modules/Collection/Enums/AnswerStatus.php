<?php

namespace App\Modules\Collection\Enums;

enum AnswerStatus: string
{
    case Collected = 'collected';
    case Failed = 'failed';
}
