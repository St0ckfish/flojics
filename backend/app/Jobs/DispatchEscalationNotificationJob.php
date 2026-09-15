<?php

namespace App\Jobs;

use App\Enums\NotificationLogStatus;
use App\Models\NotificationLog;
use App\Models\Ticket;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchEscalationNotificationJob implements ShouldQueue
{
    use Queueable;

    /** Total delivery attempts, including the first run. */
    public int $tries = 3;

    public function __construct(
        public readonly int $ticketId,
        public readonly string $channelKey,
        public readonly int $logId,
    ) {}

    /**
     * Delays between the three attempts (two waits).
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(EscalationChannelRegistry $registry): void
    {
        $ticket = Ticket::query()->findOrFail($this->ticketId);
        $log = NotificationLog::query()->findOrFail($this->logId);

        if ($log->status === NotificationLogStatus::Sent
            || $log->status === NotificationLogStatus::Failed) {
            return;
        }

        $log->increment('attempts');
        $log->refresh();

        try {
            $registry->resolve($this->channelKey)->send($ticket);
        } catch (Throwable $e) {
            $log->update(['error_message' => $e->getMessage()]);

            throw $e;
        }

        $log->update([
            'status' => NotificationLogStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    public function failed(?Throwable $e): void
    {
        $log = NotificationLog::query()->find($this->logId);

        NotificationLog::query()->whereKey($this->logId)->update([
            'status' => NotificationLogStatus::Failed,
            'error_message' => $e?->getMessage() ?? $log?->error_message,
        ]);

        Log::warning('Escalation notification exhausted retries', [
            'ticket_id' => $this->ticketId,
            'channel' => $this->channelKey,
            'notification_log_id' => $this->logId,
            'attempts' => $log?->attempts,
            'error' => $e?->getMessage(),
        ]);
    }
}
