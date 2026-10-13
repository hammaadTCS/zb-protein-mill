<?php

use App\Models\User;

it('BR-USR-06 registration route does not exist', function () {
    $this->get('/register')->assertNotFound();
});

it('BR-USR-06 password reset routes do not exist', function () {
    $this->get('/forgot-password')->assertNotFound();
    $this->get('/reset-password/x')->assertNotFound();
});

it('BR-USR-03 there is no delete account route', function () {
    $status = $this->actingAs(User::factory()->create())
        ->delete('/settings/profile', ['password' => 'password'])
        ->getStatusCode();

    expect($status)->toBeIn([404, 405]);
});

it('BR-SEC-03 login is throttled after 5 failures', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertTooManyRequests();
    $this->assertGuest();
});

it('the home page sends guests to login', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect('/login');
});
