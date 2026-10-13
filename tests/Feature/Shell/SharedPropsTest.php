<?php

use App\Models\User;

it('conventions §10 shares only id, name, email, roles and permissions of the user', function () {
    $owner = owner();

    $this->actingAs($owner)->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('auth.user.id', $owner->id)
        ->where('auth.user.name', $owner->name)
        ->where('auth.user.roles', ['owner'])
        ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('users.manage'))
        ->missing('auth.user.password')
        ->missing('auth.user.two_factor_secret')
        ->missing('auth.user.remember_token'));
});

it('conventions §10 shares the accountant permissions without owner-only keys', function () {
    $this->actingAs(accountant())->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('auth.user.permissions', fn ($permissions) => collect($permissions)->contains('purchase.create')
            && ! collect($permissions)->contains('payment.approve')));
});

it('conventions §10 shares the display formats from the settings', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertInertia(fn ($page) => $page
        ->where('settings.formats.kg_decimals', 3)
        ->where('settings.formats.rate_decimals', 2)
        ->where('settings.formats.amount_rounding', 'two_decimals')
        ->where('settings.formats.number_format', 'lakh')
        ->where('settings.formats.date_format', 'd-m-Y'));
});

it('guests get no user and no formats', function () {
    $this->get('/login')->assertInertia(fn ($page) => $page
        ->where('auth.user', null)
        ->where('settings.formats', null));
});
