<?php

namespace App\Exceptions;

use RuntimeException;

class UnsupportedEscalationChannelException extends RuntimeException
{
    public function __construct(string $channel)
    {
        parent::__construct("Notification channel [{$channel}] is not supported.");
    }
}
