<?php

namespace NoriaLabs\Send\Tests;

use NoriaLabs\Send\Providers\SendServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [SendServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('noria-send.url', 'https://send.noria.test');
        $app['config']->set('noria-send.key', 'nm_test_abcdefghijklmnopqrstuvwx');
        $app['config']->set('noria-send.webhook_secret', 'whsec_testsecret');

        $app['config']->set('mail.default', 'noria');
        $app['config']->set('mail.mailers.noria', ['transport' => 'noria']);
        $app['config']->set('mail.from', ['address' => 'hello@example.test', 'name' => 'Noria']);
    }
}
