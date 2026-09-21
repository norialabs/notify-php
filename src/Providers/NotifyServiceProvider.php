<?php

namespace NoriaLabs\Notify\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use NoriaLabs\Notify\Notifications\NotifySmsChannel;
use NoriaLabs\Notify\Notify;
use NoriaLabs\Notify\NotifyTransport;
use NoriaLabs\Notify\WebhookVerifier;

class NotifyServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/noria-notify.php', 'noria-notify');

        $this->app->singleton(Notify::class, function (): Notify {
            /** @var array{key: string, url: string, timeout: int, retries: int} $config */
            $config = $this->app->make('config')->get('noria-notify');

            return new Notify(
                $this->app->make(Factory::class),
                $config['key'],
                $config['url'],
                $config['timeout'],
                $config['retries'],
            );
        });

        $this->app->singleton(NotifySmsChannel::class, function (): NotifySmsChannel {
            /** @var array{fail_on_suppressed: bool} $config */
            $config = $this->app->make('config')->get('noria-notify');

            return new NotifySmsChannel($this->app->make(Notify::class), (bool) $config['fail_on_suppressed']);
        });

        $this->app->singleton(WebhookVerifier::class, function (): WebhookVerifier {
            /** @var array{webhook_secret: string, webhook_tolerance: int} $config */
            $config = $this->app->make('config')->get('noria-notify');

            return new WebhookVerifier($config['webhook_secret'], $config['webhook_tolerance']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/noria-notify.php' => $this->app->configPath('noria-notify.php'),
        ], 'noria-notify-config');

        Mail::extend('notify', function (array $config): NotifyTransport {
            $client = isset($config['key']) && is_string($config['key']) && $config['key'] !== ''
                ? new Notify(
                    $this->app->make(Factory::class),
                    $config['key'],
                    is_string($config['url'] ?? null) ? $config['url'] : 'http://localhost:4800',
                )
                : $this->app->make(Notify::class);

            return new NotifyTransport($client, (bool) ($config['fail_on_suppressed'] ?? false));
        });

        Notification::extend('notify-sms', fn () => $this->app->make(NotifySmsChannel::class));
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [Notify::class, NotifySmsChannel::class, WebhookVerifier::class];
    }
}
