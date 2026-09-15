<?php

namespace App\Models;

use App\Enums\NotificationLogStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $channel
 * @property NotificationLogStatus $status
 * @property int $attempts
 * @property string|null $error_message
 * @property Carbon|null $sent_at
 */
class NotificationLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'channel',
        'status',
        'attempts',
        'error_message',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => NotificationLogStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
