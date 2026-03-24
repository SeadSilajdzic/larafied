<?php

declare(strict_types=1);

use Larafied\Exceptions\SsrfException;
use Larafied\Services\SsrfGuard;

it('allows valid public urls', function () {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate('https://api.github.com/users'))->not->toThrow(SsrfException::class);
    expect(fn () => $guard->validate('http://example.com/api'))->not->toThrow(SsrfException::class);
});

it('blocks private ip ranges', function (string $url) {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate($url))->toThrow(SsrfException::class);
})->with([
    'class A private'    => ['http://10.0.0.1/secret'],
    'class B private'    => ['http://172.16.0.1/admin'],
    'class C private'    => ['http://192.168.1.1/internal'],
    'loopback'           => ['http://127.0.0.1/local'],
    'loopback localhost' => ['http://localhost/local'],
]);

it('blocks non-http schemes', function (string $url) {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate($url))->toThrow(SsrfException::class);
})->with([
    'file scheme'   => ['file:///etc/passwd'],
    'gopher scheme' => ['gopher://evil.com'],
    'ftp scheme'    => ['ftp://files.internal'],
]);

it('blocks aws metadata endpoint', function () {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate('http://169.254.169.254/latest/meta-data'))
        ->toThrow(SsrfException::class);
});

it('blocks invalid urls', function () {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate('not-a-url'))->toThrow(SsrfException::class);
    expect(fn () => $guard->validate(''))->toThrow(SsrfException::class);
});

it('blocks ipv6 loopback', function () {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate('http://[::1]/admin'))->toThrow(SsrfException::class);
});

it('blocks ipv6 private and reserved ranges', function (string $url) {
    $guard = new SsrfGuard();

    expect(fn () => $guard->validate($url))->toThrow(SsrfException::class);
})->with([
    'unique local fc'       => ['http://[fc00::1]/admin'],
    'unique local fd'       => ['http://[fd12:3456:789a::1]/internal'],
    'link-local'            => ['http://[fe80::1]/local'],
    'ipv4-mapped private'   => ['http://[::ffff:192.168.1.1]/admin'],
    'ipv4-mapped loopback'  => ['http://[::ffff:127.0.0.1]/local'],
]);
