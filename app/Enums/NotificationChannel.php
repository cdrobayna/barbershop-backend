<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Email = 'mail';
    case InApp = 'database';
}
