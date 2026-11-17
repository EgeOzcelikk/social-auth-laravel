<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Files go to a temporary fake disk, never to the real storage folder.
    Storage::fake('public');
});

// fake()->create() sets the MIME type directly, so the tests don't need the GD extension.
function fakePhoto(string $name = 'photo.jpg', int $kilobytes = 100, string $mime = 'image/jpeg'): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, $mime);
}

test('a profile photo can be uploaded', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile/photo', ['photo' => fakePhoto()])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    expect($user->avatar_path)->toStartWith('avatars/');
    Storage::disk('public')->assertExists($user->avatar_path);
});

test('uploading a new photo deletes the old file', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/photo', ['photo' => fakePhoto()]);
    $oldPath = $user->refresh()->avatar_path;

    $this->actingAs($user)->post('/profile/photo', ['photo' => fakePhoto('new.png', 100, 'image/png')]);
    $newPath = $user->refresh()->avatar_path;

    expect($newPath)->not->toBe($oldPath);
    Storage::disk('public')->assertMissing($oldPath);
    Storage::disk('public')->assertExists($newPath);
});

test('a profile photo can be removed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/photo', ['photo' => fakePhoto()]);
    $path = $user->refresh()->avatar_path;

    $this->actingAs($user)
        ->delete('/profile/photo')
        ->assertRedirect('/profile');

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

test('invalid files are rejected', function (UploadedFile $file) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->post('/profile/photo', ['photo' => $file])
        ->assertSessionHasErrorsIn('updatePhoto', 'photo')
        ->assertRedirect('/profile');

    expect($user->refresh()->avatar_path)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
})->with([
    'too large' => fn () => fakePhoto('big.jpg', 2049),
    'not an image' => fn () => fakePhoto('notes.pdf', 100, 'application/pdf'),
    'svg' => fn () => fakePhoto('logo.svg', 10, 'image/svg+xml'),
]);

test('a photo is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/profile/photo')
        ->assertSessionHasErrorsIn('updatePhoto', 'photo');
});

test('guests cannot upload or remove a photo', function () {
    $this->post('/profile/photo', ['photo' => fakePhoto()])->assertRedirect('/login');
    $this->delete('/profile/photo')->assertRedirect('/login');

    expect(Storage::disk('public')->allFiles())->toBeEmpty();
});

test('the photo file is deleted with the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/photo', ['photo' => fakePhoto()]);
    $path = $user->refresh()->avatar_path;

    $this->actingAs($user)
        ->delete('/profile', ['password' => 'password'])
        ->assertRedirect('/');

    Storage::disk('public')->assertMissing($path);
});

test('the avatar shows the initial when there is no photo', function () {
    $user = User::factory()->create(['name' => 'ada Lovelace']);

    $this->blade('<x-user-avatar :user="$user" />', ['user' => $user])
        ->assertSeeText('A')
        ->assertDontSee('<img', false);
});

test('the uploaded photo is shown on the profile page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/profile/photo', ['photo' => fakePhoto()]);
    $user->refresh();

    $this->actingAs($user)
        ->get('/profile')
        ->assertSee($user->profilePhotoUrl(), false);
});
