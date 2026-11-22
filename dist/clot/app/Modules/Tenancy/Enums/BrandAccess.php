<?php

namespace App\Modules\Tenancy\Enums;

enum BrandAccess: string
{
    /** Member sees every brand in the workspace. */
    case All = 'all';

    /** Member only sees brands granted through one of their teams. */
    case TeamsOnly = 'teams';
}
