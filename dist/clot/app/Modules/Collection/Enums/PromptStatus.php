<?php

namespace App\Modules\Collection\Enums;

enum PromptStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
}
