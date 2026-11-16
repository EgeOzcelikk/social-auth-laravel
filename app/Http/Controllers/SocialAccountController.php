<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Auth\SocialiteController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SocialAccountController extends Controller
{
    /**
     * Disconnect one of the current user's provider accounts.
     */
    public function destroy(Request $request, int $socialAccount): RedirectResponse
    {
        $user = $request->user();

        // Query through the relation: another user's account ID gives a 404,
        // so nobody can disconnect (or even detect) accounts they don't own.
        $account = $user->socialAccounts()->findOrFail($socialAccount);

        // Never remove the last way to log in.
        if (! $user->canDisconnectSocialAccount()) {
            return redirect()->route('profile.edit')->withErrors([
                'social' => __('Set a password or connect another account before disconnecting your only login method.'),
            ]);
        }

        $account->delete();

        return redirect()->route('profile.edit')->with('social-status', __(':provider account disconnected.', [
            'provider' => SocialiteController::PROVIDERS[$account->provider],
        ]));
    }
}
