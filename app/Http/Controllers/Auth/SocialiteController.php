<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as ProviderUser;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

class SocialiteController extends Controller
{
    /**
     * OAuth providers users can log in with (route value => display name).
     * Routes reject any other value.
     */
    public const PROVIDERS = ['google' => 'Google', 'github' => 'GitHub'];

    /**
     * Send the user to the provider's consent screen.
     */
    public function redirect(string $provider): SymfonyRedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the provider's response and log the user in.
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Exception $e) {
            // Invalid state, cancelled consent or a provider error: log it, then tell the user.
            Log::warning('OAuth callback failed.', [
                'provider' => $provider,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return $this->failed(__('Login with :provider failed. Please try again.', ['provider' => self::PROVIDERS[$provider]]));
        }

        // Returning user: the provider account is already linked.
        $account = SocialAccount::where('provider', $provider)
            ->where('provider_id', $providerUser->getId())
            ->first();

        if ($account) {
            return $this->login($account->user);
        }

        $email = $this->verifiedEmail($provider, $providerUser);

        if ($email === null) {
            return $this->failed(__('Your :provider account has no verified email address.', ['provider' => self::PROVIDERS[$provider]]));
        }

        $user = User::where('email', $email)->first();

        // Only link to a local account whose owner proved they own the email.
        // Otherwise someone could pre-register the victim's email with a password
        // and get access once the victim logs in with OAuth.
        if ($user && ! $user->hasVerifiedEmail()) {
            return $this->failed(__('An account with this email already exists. Please log in with your password.'));
        }

        $user = DB::transaction(function () use ($user, $provider, $providerUser, $email) {
            $user ??= User::forceCreate([
                'name' => $providerUser->getName() ?? $providerUser->getNickname() ?? $email,
                'email' => $email,
                'email_verified_at' => now(),
            ]);

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_id' => $providerUser->getId(),
            ]);

            return $user;
        });

        return $this->login($user);
    }

    /**
     * Return the provider email only if the provider says it is verified.
     */
    private function verifiedEmail(string $provider, ProviderUser $providerUser): ?string
    {
        return match ($provider) {
            // Google reports verification in the userinfo response.
            'google' => ($providerUser->getRaw()['email_verified'] ?? false) === true
                ? $providerUser->getEmail()
                : null,
            // Socialite only returns the primary GitHub email if it is verified, otherwise null.
            'github' => $providerUser->getEmail(),
        };
    }

    private function login(User $user): RedirectResponse
    {
        // Auth::login() also regenerates the session ID (prevents session fixation).
        Auth::login($user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['oauth' => $message]);
    }
}
