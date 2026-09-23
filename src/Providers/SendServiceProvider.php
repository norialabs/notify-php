<?php

namespace NoriaLabs\Send\Providers;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use NoriaLabs\Send\Notifications\SendSmsChannel;
use NoriaLabs\Send\Send;
use NoriaLabs\Send\SendTransport;
use NoriaLabs\Send\WebhookVerifier;

class SendServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/noria-send.php', 'noria-send');

        $this->app->singleton(Send::class, function (): Send {
            /** @var array{key: string, url: string, timeout: int, retries: int} $config */
            $config = $this->app->make('config')->get('noria-send');

            return new Send(
                $this->app->make(Factory::class),
                $config['key'],
                $config['url'],
                $config['timeout'],
                $config['retries'],
            );
        });

        $this->app->singleton(SendSmsChannel::class, function (): SendSmsChannel {
            /** @var array{fail_on_suppressed: bool} $config */
            $config = $this->app->make('config')->get('noria-send');

            return new SendSmsChannel($this->app->make(Send::class), (bool) $config['fail_on_suppressed']);
        });

        $this->app->singleton(WebhookVerifier::class, function (): WebhookVerifier {
            /** @var array{webhook_secret: string, webhook_tolerance: int} $config */
            $config = $this->app->make('config')->get('noria-send');

            return new WebhookVerifier($config['webhook_secret'], $config['webhook_tolerance']);
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/noria-send.php' => $this->app->configPath('noria-send.php'),
        ], 'noria-send-config');

        Mail::extend('noria', function (array $config): SendTransport {
            /** @var array{timeout: int, retries: int} $defaults */
            $defaults = $this->app->make('config')->get('noria-send');

            $client = isset($config['key']) && is_string($config['key']) && $config['key'] !== ''
                ? new Send(
                    $this->app->make(Factory::class),
                    $config['key'],
                    is_string($config['url'] ?? null) ? $config['url'] : Send::DEFAULT_BASE_URL,
                    is_int($config['timeout'] ?? null) ? $config['timeout'] : $defaults['timeout'],
                    is_int($config['retries'] ?? null) ? $config['retries'] : $defaults['retries'],
                )
                : $this->app->make(Send::class);

            return new SendTransport($client, (bool) ($config['fail_on_suppressed'] ?? false));
        });

        // The channel manager binds this closure to itself, so $this inside it is the manager,
        // not the provider, and from Laravel 13 the manager has no $app property.
        $app = $this->app;
        Notification::extend('send-sms', fn (): SendSmsChannel => $app->make(SendSmsChannel::class));
    }
}
