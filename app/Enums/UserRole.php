<?php

namespace App\Enums;

enum UserRole: string
{
    case Provider = 'provider';
    case Client = 'client';
}
