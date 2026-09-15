<?php

namespace App\NotificationChannels;

use App\Exceptions\UnsupportedEscalationChannelException;
use App\NotificationChannels\Contracts\EscalationChannel;

class EscalationChannelRegistry
{
    /**
     * @param  list<EscalationChannel>  $channels
     */
    public function __construct(private readonly array $channels) {}

    public function resolve(string $key): EscalationChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->key() === $key) {
                return $channel;
            }
        }

        throw new UnsupportedEscalationChannelException($key);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_map(fn (EscalationChannel $channel) => $channel->key(), $this->channels);
    }
}
