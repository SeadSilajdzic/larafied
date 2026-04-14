<?php

declare(strict_types=1);

it('returns disabled when broadcasting driver is null', function () {
    $this->app['config']->set('broadcasting.default', 'null');

    $this->getJson('/larafied/api/ws/config')
        ->assertOk()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('driver', 'null');
});

it('returns reverb config when driver is reverb', function () {
    $this->app['config']->set('broadcasting.default', 'reverb');
    $this->app['config']->set('broadcasting.connections.reverb', [
        'key'     => 'my-reverb-key',
        'options' => [
            'host'   => 'localhost',
            'port'   => 8080,
            'scheme' => 'http',
        ],
    ]);

    $this->getJson('/larafied/api/ws/config')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('driver', 'reverb')
        ->assertJsonPath('host', 'localhost')
        ->assertJsonPath('port', 8080)
        ->assertJsonPath('scheme', 'http')
        ->assertJsonPath('app_key', 'my-reverb-key')
        ->assertJsonPath('path', '/app');
});

it('returns pusher config when driver is pusher', function () {
    $this->app['config']->set('broadcasting.default', 'pusher');
    $this->app['config']->set('broadcasting.connections.pusher', [
        'key'     => 'pusher-key',
        'options' => [
            'cluster' => 'eu',
            'useTLS'  => true,
            'port'    => 443,
        ],
    ]);

    $this->getJson('/larafied/api/ws/config')
        ->assertOk()
        ->assertJsonPath('enabled', true)
        ->assertJsonPath('driver', 'pusher')
        ->assertJsonPath('app_key', 'pusher-key')
        ->assertJsonPath('scheme', 'https')
        ->assertJsonPath('port', 443)
        ->assertJsonPath('cluster', 'eu');
});

it('returns pusher with non-tls config', function () {
    $this->app['config']->set('broadcasting.default', 'pusher');
    $this->app['config']->set('broadcasting.connections.pusher', [
        'key'     => 'pk',
        'options' => ['useTLS' => false],
    ]);

    $this->getJson('/larafied/api/ws/config')
        ->assertOk()
        ->assertJsonPath('scheme', 'http')
        ->assertJsonPath('port', 80);
});

it('returns disabled for unsupported driver', function () {
    $this->app['config']->set('broadcasting.default', 'ably');

    $this->getJson('/larafied/api/ws/config')
        ->assertOk()
        ->assertJsonPath('enabled', false)
        ->assertJsonPath('driver', 'ably');
});
