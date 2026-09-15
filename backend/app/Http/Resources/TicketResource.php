<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Ticket
 */
class TicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'escalated_at' => $this->escalated_at?->toIso8601String(),
            'customer_id' => $this->customer_id,
            'agent_id' => $this->agent_id,
            'notification_logs' => NotificationLogResource::collection(
                $this->whenLoaded('notificationLogs'),
            ),
        ];
    }
}
