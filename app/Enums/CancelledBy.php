<?php

namespace App\Enums;

enum CancelledBy: string
{
    case Provider = 'provider';
    case Client = 'client';
}
