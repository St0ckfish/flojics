<?php

namespace App\Providers;

use App\NotificationChannels\Contracts\EscalationChannel;
use App\NotificationChannels\EscalationChannelRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EscalationChannelRegistry::class, function (Application $app): EscalationChannelRegistry {
            /** @var array<string, class-string<EscalationChannel>> $map */
            $map = config('escalation.channels', []);

            $channels = [];

            foreach ($map as $key => $class) {
                $channel = $app->make($class);

                if (! $channel instanceof EscalationChannel) {
                    throw new InvalidArgumentException("Escalation channel [{$key}] must implement EscalationChannel.");
                }

                if ($channel->key() !== $key) {
                    throw new InvalidArgumentException("Escalation channel [{$key}] reports key [{$channel->key()}].");
                }

                $channels[] = $channel;
            }

            return new EscalationChannelRegistry($channels);
        });
    }

    public function boot(): void
    {
        //
    }
}
