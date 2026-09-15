<?php

namespace App\Jobs;

use App\Enums\NotificationLogStatus;
use App\Models\NotificationLog;
use App\Models\Ticket;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DispatchEscalationNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $channelKey,
        public readonly int $logId,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(EscalationChannelRegistry $registry): void
    {
        $ticket = Ticket::query()->findOrFail($this->ticketId);
        $log = NotificationLog::query()->findOrFail($this->logId);

        if ($log->status === NotificationLogStatus::Sent) {
            return;
        }

        $log->increment('attempts');
        $log->refresh();

        $registry->resolve($this->channelKey)->send($ticket);

        $log->update([
            'status' => NotificationLogStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        NotificationLog::query()->whereKey($this->logId)->update([
            'status' => NotificationLogStatus::Failed,
            'error_message' => $e?->getMessage(),
        ]);
    }
}
