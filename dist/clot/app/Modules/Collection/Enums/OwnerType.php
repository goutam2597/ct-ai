<?php

namespace App\Modules\Collection\Enums;

enum OwnerType: string
{
    case You = 'you';
    case Competitor = 'competitor';
    case ThirdParty = 'third_party';
}
