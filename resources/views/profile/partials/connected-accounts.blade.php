<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Connected Accounts') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('Connect a Google or GitHub account to log in with it.') }}
        </p>
    </header>

    @if (session('social-status'))
        <p class="mt-4 text-sm font-medium text-green-600">{{ session('social-status') }}</p>
    @endif

    <x-input-error :messages="$errors->get('social')" class="mt-4" />

    <ul class="mt-6 divide-y divide-gray-100">
        @foreach (\App\Http\Controllers\Auth\SocialiteController::PROVIDERS as $provider => $name)
            @php($account = $socialAccounts->get($provider))

            <li class="flex items-center justify-between py-3">
                <div>
                    <p class="font-medium text-gray-900">{{ $name }}</p>
                    <p class="text-sm text-gray-600">{{ $account ? __('Connected') : __('Not connected') }}</p>
                </div>

                @if ($account)
                    @if ($user->canDisconnectSocialAccount())
                        <form method="post" action="{{ route('social-accounts.destroy', $account->id) }}">
                            @csrf
                            @method('delete')

                            <x-danger-button>{{ __('Disconnect') }}</x-danger-button>
                        </form>
                    @else
                        <p class="max-w-48 text-right text-xs text-gray-500">
                            {{ __('This is your only login method.') }}
                        </p>
                    @endif
                @else
                    <a href="{{ route('socialite.redirect', $provider) }}"
                       class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        {{ __('Connect') }}
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</section>
