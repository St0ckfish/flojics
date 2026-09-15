<?php

namespace App\Exceptions;

use RuntimeException;

class TicketAlreadyEscalatedException extends RuntimeException
{
    public function __construct(int $ticketId)
    {
        parent::__construct("Ticket [{$ticketId}] is already escalated.");
    }
}
