<?php

namespace App\Enums;

enum EscalationChannelKey: string
{
    case Email = 'email';
    case Slack = 'slack';
}
