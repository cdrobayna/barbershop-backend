<?php

namespace App\Enums;

enum RescheduleRequestedBy: string
{
    case Provider = 'provider';
    case System = 'system';
}
