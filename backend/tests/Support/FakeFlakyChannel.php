<?php

namespace Tests\Support;

use App\Models\Ticket;
use App\NotificationChannels\Contracts\EscalationChannel;
use RuntimeException;

class FakeFlakyChannel implements EscalationChannel
{
    public int $sendCount = 0;

    public function __construct(
        private readonly string $channelKey = 'email',
        private readonly int $failuresBeforeSuccess = 0,
        private readonly string $errorMessage = 'Channel failed',
    ) {}

    public function key(): string
    {
        return $this->channelKey;
    }

    public function send(Ticket $ticket): void
    {
        $this->sendCount++;

        if ($this->sendCount <= $this->failuresBeforeSuccess) {
            throw new RuntimeException($this->errorMessage);
        }
    }
}
