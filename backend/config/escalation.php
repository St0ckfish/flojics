<?php

use App\NotificationChannels\EmailEscalationChannel;
use App\NotificationChannels\SlackEscalationChannel;

return [

    'mail_to' => env('ESCALATION_MAIL_TO', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    'slack_channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL') ?: '#escalations',

    /*
    | Explicit channel map. Adding WhatsApp is a class + one line here.
    | EscalateTicketAction and the controller do not switch on channel names.
    */
    'channels' => [
        'email' => EmailEscalationChannel::class,
        'slack' => SlackEscalationChannel::class,
    ],

];
