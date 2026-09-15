<?php

namespace App\Providers;

use App\NotificationChannels\EmailEscalationChannel;
use App\NotificationChannels\EscalationChannelRegistry;
use App\NotificationChannels\SlackEscalationChannel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EscalationChannelRegistry::class, function (Application $app): EscalationChannelRegistry {
            return new EscalationChannelRegistry([
                $app->make(EmailEscalationChannel::class),
                $app->make(SlackEscalationChannel::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
