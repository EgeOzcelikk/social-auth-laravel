<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilePhotoController extends Controller
{
    /**
     * Upload a new profile photo, replacing the old one.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validateWithBag('updatePhoto', [
            // "image" + "mimes" check the real file content, not the name the client sent.
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.max' => __('The photo must not be larger than 2 MB.'),
        ]);

        $user = $request->user();
        $oldPath = $user->avatar_path;

        // Random file name: no user input ends up in the path.
        $user->avatar_path = $request->file('photo')->store('avatars', 'public');
        $user->save();

        // Delete the old file only after the new one is saved,
        // so a failed upload never leaves the user without a photo.
        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return redirect()->route('profile.edit')->with('status', 'photo-updated');
    }

    /**
     * Remove the profile photo.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->deleteProfilePhotoFile();
        $user->avatar_path = null;
        $user->save();

        return redirect()->route('profile.edit')->with('status', 'photo-removed');
    }
}
