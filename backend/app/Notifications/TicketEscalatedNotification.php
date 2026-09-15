<?php

namespace App\Notifications;

use App\Enums\EscalationChannelKey;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class TicketEscalatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly EscalationChannelKey $channel,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return match ($this->channel) {
            EscalationChannelKey::Email => ['mail'],
            EscalationChannelKey::Slack => ['slack'],
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Ticket #{$this->ticket->id} escalated")
            ->line("Ticket #{$this->ticket->id} has been escalated.")
            ->line("Subject: {$this->ticket->subject}")
            ->line('Priority: '.$this->ticket->priority->value)
            ->line('Status: '.$this->ticket->status->value);
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        $ticket = $this->ticket;

        return (new SlackMessage)
            ->text("Ticket #{$ticket->id} has been escalated: {$ticket->subject}")
            ->headerBlock('Ticket escalated')
            ->sectionBlock(function (SectionBlock $block) use ($ticket): void {
                $block->text("*#{$ticket->id}* — {$ticket->subject}")->markdown();
                $block->field("*Priority*\n{$ticket->priority->value}")->markdown();
                $block->field("*Status*\n{$ticket->status->value}")->markdown();
            });
    }
}
