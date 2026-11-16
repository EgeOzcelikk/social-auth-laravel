<x-guest-layout>
    <h1 class="text-xl font-semibold text-gray-900">{{ config('app.name') }}</h1>
    <p class="mt-2 text-sm text-gray-600">
        {{ __('Sign in with email and password, or continue with Google or GitHub.') }}
    </p>

    @auth
        <div class="mt-6">
            <a href="{{ route('dashboard') }}"
               class="inline-flex w-full justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Go to dashboard') }}
            </a>
        </div>
    @else
        <div class="mt-6 grid grid-cols-2 gap-3">
            <a href="{{ route('login') }}"
               class="inline-flex justify-center rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Log in') }}
            </a>
            <a href="{{ route('register') }}"
               class="inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                {{ __('Register') }}
            </a>
        </div>

        <x-social-login-buttons />
    @endauth
</x-guest-layout>
