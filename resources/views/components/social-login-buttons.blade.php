<div class="mt-6">
    <x-input-error :messages="$errors->get('oauth')" class="mb-4" />

    <div class="flex items-center gap-3">
        <div class="h-px flex-1 bg-gray-200"></div>
        <span class="text-sm text-gray-500">{{ __('or') }}</span>
        <div class="h-px flex-1 bg-gray-200"></div>
    </div>

    <div class="mt-4 grid gap-3">
        @foreach (\App\Http\Controllers\Auth\SocialiteController::PROVIDERS as $provider => $label)
            <a href="{{ route('socialite.redirect', $provider) }}"
               class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Continue with :provider', ['provider' => $label]) }}
            </a>
        @endforeach
    </div>
</div>
