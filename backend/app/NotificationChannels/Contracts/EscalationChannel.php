<?php

namespace App\NotificationChannels\Contracts;

use App\Models\Ticket;
use Throwable;

interface EscalationChannel
{
    public function key(): string;

    /**
     * Deliver the escalation notice. Must throw on transport failure
     * so the queued job can retry.
     *
     * @throws Throwable
     */
    public function send(Ticket $ticket): void;
}
