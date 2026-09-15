<?php

namespace App\Http\Resources;

use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationLog
 */
class NotificationLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
