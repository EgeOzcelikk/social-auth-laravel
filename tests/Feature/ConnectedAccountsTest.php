<?php

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

// --- Profile page ---------------------------------------------------------

test('the profile page shows a connect link for each provider', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/profile')
        ->assertOk()
        ->assertSee('Connected Accounts')
        ->assertSee(route('socialite.redirect', 'google'))
        ->assertSee(route('socialite.redirect', 'github'));
});

test('the profile page shows a disconnect button for connected providers', function () {
    $user = User::factory()->create();
    $account = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->actingAs($user)->get('/profile')
        ->assertSee(route('social-accounts.destroy', $account->id))
        ->assertDontSee(route('socialite.redirect', 'github'))
        ->assertSee(route('socialite.redirect', 'google'));
});

test('the disconnect button is hidden for the only login method', function () {
    $user = User::factory()->create(['password' => null]);
    $account = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->actingAs($user)->get('/profile')
        ->assertDontSee(route('social-accounts.destroy', $account->id))
        ->assertSee('This is your only login method.');
});

// --- Connect --------------------------------------------------------------

test('a logged-in user can connect a provider account with a different email', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat', 'email' => 'other@example.com']);

    $this->actingAs($user)->get('/auth/github/callback')
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('social-status', 'GitHub account connected.');

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => '42',
    ]);
    expect(User::count())->toBe(1);
});

test('connecting an account that is already connected to the same user changes nothing', function () {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat']);

    $this->actingAs($user)->get('/auth/github/callback')
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('social-status', 'GitHub account is already connected.');

    expect($user->socialAccounts()->count())->toBe(1);
});

test('an account connected to another user cannot be connected', function () {
    $owner = User::factory()->create();
    $owner->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);
    $user = User::factory()->create();
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat']);

    $this->actingAs($user)->get('/auth/github/callback')
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['social' => 'This GitHub account is already connected to another user.']);

    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('social_accounts', ['provider_id' => '42', 'user_id' => $owner->id]);
});

test('a user cannot connect a second account of the same provider', function () {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);
    fakeProviderUser('google', ['id' => 'g-2', 'email' => 'second@example.com'], ['email_verified' => true]);

    $this->actingAs($user)->get('/auth/google/callback')
        ->assertSessionHasErrors(['social' => 'You already have a Google account connected. Disconnect it first.']);

    expect($user->socialAccounts()->pluck('provider_id')->all())->toBe(['g-1']);
});

test('the database rejects a second account of the same provider for one user', function () {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);

    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-2']);
})->throws(UniqueConstraintViolationException::class);

test('a user with an unverified email cannot connect accounts', function () {
    $user = User::factory()->unverified()->create();
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat']);

    $this->actingAs($user)->get('/auth/github/callback')
        ->assertSessionHasErrors(['social' => 'Please verify your email address before connecting accounts.']);

    $this->assertDatabaseCount('social_accounts', 0);
});

test('a failed provider callback while logged in returns to the profile with an error', function () {
    $user = User::factory()->create();
    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->actingAs($user)->get('/auth/github/callback')
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors(['social' => 'Connecting GitHub failed. Please try again.']);

    $this->assertAuthenticatedAs($user);
});

test('a logged-in user can start the connect flow', function () {
    $response = $this->actingAs(User::factory()->create())->get('/auth/github/redirect');

    expect($response->headers->get('Location'))->toStartWith('https://github.com/login/oauth/authorize');
});

// --- Disconnect -----------------------------------------------------------

test('a user with a password can disconnect their only provider account', function () {
    $user = User::factory()->create();
    $account = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->actingAs($user)->delete(route('social-accounts.destroy', $account->id))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('social-status', 'GitHub account disconnected.');

    $this->assertModelMissing($account);
});

test('an oauth-only user cannot disconnect their last login method', function () {
    $user = User::factory()->create(['password' => null]);
    $account = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->actingAs($user)->delete(route('social-accounts.destroy', $account->id))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasErrors('social');

    $this->assertModelExists($account);
});

test('an oauth-only user with two accounts can disconnect one', function () {
    $user = User::factory()->create(['password' => null]);
    $github = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1']);

    $this->actingAs($user)->delete(route('social-accounts.destroy', $github->id))
        ->assertSessionHasNoErrors();

    $this->assertModelMissing($github);
});

test('a user cannot disconnect another user\'s account', function () {
    $owner = User::factory()->create();
    $account = $owner->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->actingAs(User::factory()->create())
        ->delete(route('social-accounts.destroy', $account->id))
        ->assertNotFound();

    $this->assertModelExists($account);
});

test('guests cannot disconnect accounts', function () {
    $account = User::factory()->create()->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this->delete(route('social-accounts.destroy', $account->id))->assertRedirect(route('login'));

    $this->assertModelExists($account);
});
