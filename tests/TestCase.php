<?php

declare(strict_types=1);

namespace Larafied\Tests;

use Larafied\LarafiedServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LarafiedServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.env', 'local');
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('larafied.allowed_environments', ['local']);
        $app['config']->set('larafied.allow_private_hosts', false);
    }
}
