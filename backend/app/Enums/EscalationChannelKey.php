<?php

namespace App\Enums;

enum EscalationChannelKey: string
{
    case Email = 'email';
    case Slack = 'slack';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
