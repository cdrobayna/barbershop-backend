<?php

namespace App\Exceptions;

use Exception;

class AppointmentActionNotAllowedException extends Exception
{
    public function __construct(string $message = 'This action is not allowed for the current appointment status.')
    {
        parent::__construct($message);
    }
}
