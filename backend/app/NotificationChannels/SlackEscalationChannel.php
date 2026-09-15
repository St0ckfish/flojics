<?php

namespace App\NotificationChannels;

use App\Enums\EscalationChannelKey;
use App\Models\Ticket;
use App\NotificationChannels\Contracts\EscalationChannel;
use App\Notifications\TicketEscalatedNotification;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

class SlackEscalationChannel implements EscalationChannel
{
    public function key(): string
    {
        return EscalationChannelKey::Slack->value;
    }

    public function send(Ticket $ticket): void
    {
        $token = config('services.slack.notifications.bot_user_oauth_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Slack webhook failure: SLACK_BOT_USER_OAUTH_TOKEN is not configured.');
        }

        $channel = config('escalation.slack_channel');

        if (! is_string($channel) || $channel === '') {
            throw new RuntimeException('Slack webhook failure: no default channel is configured.');
        }

        Notification::route('slack', $channel)
            ->notify(new TicketEscalatedNotification($ticket, EscalationChannelKey::Slack));
    }
}
