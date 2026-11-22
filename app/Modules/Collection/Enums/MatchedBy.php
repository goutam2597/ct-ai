<?php

namespace App\Modules\Collection\Enums;

enum MatchedBy: string
{
    case Name = 'name';
    case Alias = 'alias';
    case Domain = 'domain';
}
