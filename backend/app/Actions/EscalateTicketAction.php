<?php

namespace App\Actions;

use App\Enums\NotificationLogStatus;
use App\Exceptions\TicketAlreadyEscalatedException;
use App\Exceptions\TicketNotFoundException;
use App\Jobs\DispatchEscalationNotificationJob;
use App\Models\Ticket;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EscalateTicketAction
{
    public function __construct(private readonly EscalationChannelRegistry $channels) {}

    /**
     * @param  list<string>  $channelKeys
     */
    public function execute(int $ticketId, array $channelKeys = []): Ticket
    {
        return DB::transaction(function () use ($ticketId, $channelKeys): Ticket {
            $ticket = Ticket::query()->lockForUpdate()->find($ticketId);

            if ($ticket === null) {
                throw new TicketNotFoundException($ticketId);
            }

            $resolvedKeys = $this->normalizeChannels($channelKeys);

            foreach ($resolvedKeys as $channelKey) {
                $this->channels->resolve($channelKey);
            }

            $ticket->markEscalated();

            foreach ($resolvedKeys as $channelKey) {
                try {
                    $log = $ticket->notificationLogs()->create([
                        'channel' => $channelKey,
                        'status' => NotificationLogStatus::Pending,
                        'attempts' => 0,
                    ]);
                } catch (UniqueConstraintViolationException) {
                    throw new TicketAlreadyEscalatedException($ticket->id);
                }

                DispatchEscalationNotificationJob::dispatch(
                    $ticket->id,
                    $channelKey,
                    $log->id,
                )->afterCommit();
            }

            return $ticket->refresh()->load('notificationLogs');
        });
    }

    /**
     * @param  list<string>  $channelKeys
     * @return list<string>
     */
    private function normalizeChannels(array $channelKeys): array
    {
        if ($channelKeys === []) {
            return $this->channels->keys();
        }

        return array_values(array_unique($channelKeys));
    }
}
