<?php

use App\Models\User;

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('shows login, register and social links to guests', function () {
    $this->get('/')
        ->assertSee(route('login'))
        ->assertSee(route('register'))
        ->assertSee(route('socialite.redirect', 'google'))
        ->assertSee(route('socialite.redirect', 'github'))
        ->assertDontSee(route('dashboard'));
});

it('shows a dashboard link to logged-in users', function () {
    $this->actingAs(User::factory()->create())
        ->get('/')
        ->assertSee(route('dashboard'))
        ->assertDontSee(route('register'));
});
