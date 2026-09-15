<?php

return [

    'mail_to' => env('ESCALATION_MAIL_TO', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    'slack_channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL', '#escalations'),

];
