<?php

namespace NoriaLabs\Notify\Tests;

use NoriaLabs\Notify\Providers\NotifyServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [NotifyServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('noria-notify.url', 'https://notify.noria.test');
        $app['config']->set('noria-notify.key', 'nm_test_abcdefghijklmnopqrstuvwx');
        $app['config']->set('noria-notify.webhook_secret', 'whsec_testsecret');

        $app['config']->set('mail.default', 'notify');
        $app['config']->set('mail.mailers.notify', ['transport' => 'notify']);
        $app['config']->set('mail.from', ['address' => 'hello@example.test', 'name' => 'Noria']);
    }
}
