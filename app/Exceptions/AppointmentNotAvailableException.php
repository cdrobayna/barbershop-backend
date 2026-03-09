<?php

namespace App\Exceptions;

use Exception;

class AppointmentNotAvailableException extends Exception
{
    public function __construct(string $message = 'The requested time slot is not available.')
    {
        parent::__construct($message);
    }
}
