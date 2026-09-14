<?php

namespace App\Enums;

enum NotificationLogStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
}
