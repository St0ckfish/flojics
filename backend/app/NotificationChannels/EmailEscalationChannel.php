<?php

namespace App\NotificationChannels;

use App\Enums\EscalationChannelKey;
use App\Models\Ticket;
use App\NotificationChannels\Contracts\EscalationChannel;
use App\Notifications\TicketEscalatedNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class EmailEscalationChannel implements EscalationChannel
{
    public function key(): string
    {
        return EscalationChannelKey::Email->value;
    }

    public function send(Ticket $ticket): void
    {
        $recipient = config('escalation.mail_to');

        if (! is_string($recipient) || $recipient === '') {
            throw new RuntimeException('Email service unavailable: no escalation recipient is configured.');
        }

        Notification::route('mail', $recipient)
            ->notify(new TicketEscalatedNotification($ticket, EscalationChannelKey::Email));
    }
}
