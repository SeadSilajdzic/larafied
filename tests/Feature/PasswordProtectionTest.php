<?php

declare(strict_types=1);

it('allows access when no password is configured', function () {
    $this->app['config']->set('larafied.password', null);

    $this->get('/larafied')->assertSuccessful();
});

it('redirects to unlock page when password is set and session is empty', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->get('/larafied')->assertRedirect('/larafied/unlock');
});

it('api routes return 401 json when password set and not authenticated', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->getJson('/larafied/api/routes')
        ->assertUnauthorized()
        ->assertJsonPath('error', 'password_required');
});

it('shows the unlock form when password is configured', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->get('/larafied/unlock')->assertSuccessful()->assertViewIs('larafied::unlock');
});

it('wrong password redirects back with error in session', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->post('/larafied/unlock', ['password' => 'wrong'])
        ->assertRedirect()
        ->assertSessionHasErrors('password');

    $this->assertFalse(session()->has('larafied_unlocked'));
});

it('correct password sets session and redirects to dashboard', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->post('/larafied/unlock', ['password' => 'secret'])
        ->assertRedirect('/larafied')
        ->assertSessionHas('larafied_unlocked', true);
});

it('allows access when larafied_unlocked session is set', function () {
    $this->app['config']->set('larafied.password', 'secret');

    $this->withSession(['larafied_unlocked' => true])
        ->get('/larafied')
        ->assertSuccessful();
});
