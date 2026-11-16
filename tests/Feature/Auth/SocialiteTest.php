<?php

use App\Models\User;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;

/**
 * Make Socialite return the given provider user on callback, without any HTTP call.
 */
function fakeProviderUser(string $provider, array $attributes, array $raw = []): void
{
    $providerUser = (new ProviderUser)->setRaw($raw)->map($attributes);

    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andReturn($providerUser);

    Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
}

test('login and register pages show the social login buttons', function (string $page) {
    $this->get($page)
        ->assertOk()
        ->assertSee(route('socialite.redirect', 'google'))
        ->assertSee(route('socialite.redirect', 'github'));
})->with(['/login', '/register']);

test('redirect sends the user to the provider', function () {
    $response = $this->get('/auth/github/redirect');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://github.com/login/oauth/authorize');
});

test('unknown providers return 404', function (string $url) {
    $this->get($url)->assertNotFound();
})->with(['/auth/facebook/redirect', '/auth/facebook/callback']);

test('a new user is created and logged in with a verified google email', function () {
    fakeProviderUser('google',
        ['id' => 'g-123', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
        ['email_verified' => true],
    );

    $this->get('/auth/google/callback')->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'ada@example.com')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect($user->name)->toBe('Ada Lovelace')
        ->and($user->password)->toBeNull()
        ->and($user->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'g-123',
    ]);
});

test('github nickname is used when the user has no display name', function () {
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat', 'name' => null, 'email' => 'octo@example.com']);

    $this->get('/auth/github/callback');

    expect(User::where('email', 'octo@example.com')->value('name'))->toBe('octocat');
});

test('a returning user is found by provider id even if their email changed', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat', 'email' => 'new@example.com']);

    $this->get('/auth/github/callback')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('the provider is linked to an existing verified user with the same email', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    fakeProviderUser('github', ['id' => 42, 'nickname' => 'ada', 'email' => 'ada@example.com']);

    $this->get('/auth/github/callback')->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1)
        ->and($user->socialAccounts()->where('provider', 'github')->exists())->toBeTrue();
});

test('an unverified local account is not linked', function () {
    $user = User::factory()->unverified()->create(['email' => 'ada@example.com']);

    fakeProviderUser('github', ['id' => 42, 'nickname' => 'ada', 'email' => 'ada@example.com']);

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    $this->assertGuest();
    expect($user->socialAccounts()->exists())->toBeFalse();
});

test('an unverified google email is rejected', function () {
    fakeProviderUser('google',
        ['id' => 'g-123', 'name' => 'Ada', 'email' => 'ada@example.com'],
        ['email_verified' => false],
    );

    $this->get('/auth/google/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('oauth');

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('a github account without a verified email is rejected', function () {
    fakeProviderUser('github', ['id' => 42, 'nickname' => 'octocat', 'email' => null]);

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['oauth' => 'Your GitHub account has no verified email address.']);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('a failed provider callback redirects to login with an error', function () {
    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('github')->andReturn($driver);

    $this->get('/auth/github/callback')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['oauth' => 'Login with GitHub failed. Please try again.']);

    $this->assertGuest();
});

test('a user without a password cannot log in with the password form', function () {
    $user = User::factory()->create(['password' => null]);

    $this->post('/login', ['email' => $user->email, 'password' => 'anything']);

    $this->assertGuest();
});
