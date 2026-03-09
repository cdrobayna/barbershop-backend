<?php

namespace App\Exceptions;

use Exception;

class ScheduleConflictException extends Exception
{
    public function __construct(string $message = 'A scheduling conflict was detected.')
    {
        parent::__construct($message);
    }
}
