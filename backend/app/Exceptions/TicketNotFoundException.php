<?php

namespace App\Exceptions;

use RuntimeException;

class TicketNotFoundException extends RuntimeException
{
    public function __construct(int $ticketId)
    {
        parent::__construct("Ticket [{$ticketId}] was not found.");
    }
}
