<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('the delete dialog asks password users for the password of this app', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertSee('Please enter the password for this app (not your Google or GitHub password)')
        ->assertDontSee('Type your email address');
});

test('the delete dialog asks oauth-only users to type their email', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => null]);

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee('Type your email address (ada@example.com) to confirm.')
        ->assertDontSee('Please enter the password for this app');
});

test('oauth-only users do not see the update password form', function () {
    $this->actingAs(User::factory()->create(['password' => null]))
        ->get('/profile')
        ->assertDontSee('Update Password')
        ->assertDontSee(route('password.update'));
});

test('password users see the update password form', function () {
    $this->actingAs(User::factory()->create())
        ->get('/profile')
        ->assertSee('Update Password')
        ->assertSee(route('password.update'));
});

test('an oauth-only user can delete their account by typing their email', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => null]);
    $account = $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);

    $this
        ->actingAs($user)
        ->delete('/profile', ['email' => 'ada@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
    // The foreign key cascades: connected provider accounts are deleted too.
    $this->assertModelMissing($account);
});

test('an oauth-only user must type the correct email to delete their account', function () {
    $user = User::factory()->create(['email' => 'ada@example.com', 'password' => null]);

    $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', ['email' => 'someone-else@example.com'])
        ->assertSessionHasErrorsIn('userDeletion', ['email' => 'The email address does not match your account.'])
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
