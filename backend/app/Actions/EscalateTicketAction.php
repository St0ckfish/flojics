<?php

namespace App\Actions;

use App\Enums\EscalationChannelKey;
use App\Enums\NotificationLogStatus;
use App\Exceptions\TicketNotFoundException;
use App\Jobs\DispatchEscalationNotificationJob;
use App\Models\Ticket;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Support\Facades\DB;

class EscalateTicketAction
{
    public function __construct(private readonly EscalationChannelRegistry $channels) {}

    /**
     * @param  list<string|EscalationChannelKey>  $channelKeys
     */
    public function execute(int $ticketId, array $channelKeys = []): Ticket
    {
        return DB::transaction(function () use ($ticketId, $channelKeys): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->find($ticketId);

            if ($ticket === null) {
                throw new TicketNotFoundException($ticketId);
            }

            $ticket->markEscalated();

            foreach ($this->normalizeChannels($channelKeys) as $channel) {
                $this->channels->resolve($channel->value);

                $log = $ticket->notificationLogs()->create([
                    'channel' => $channel->value,
                    'status' => NotificationLogStatus::Pending,
                    'attempts' => 0,
                ]);

                DispatchEscalationNotificationJob::dispatch(
                    $ticket->id,
                    $channel->value,
                    $log->id,
                )->afterCommit();
            }

            return $ticket->refresh()->load('notificationLogs');
        });
    }

    /**
     * @param  list<string|EscalationChannelKey>  $channelKeys
     * @return list<EscalationChannelKey>
     */
    private function normalizeChannels(array $channelKeys): array
    {
        if ($channelKeys === []) {
            return EscalationChannelKey::cases();
        }

        return array_map(
            fn (string|EscalationChannelKey $key): EscalationChannelKey => $key instanceof EscalationChannelKey
                ? $key
                : EscalationChannelKey::from($key),
            $channelKeys,
        );
    }
}
