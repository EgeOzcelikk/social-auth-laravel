<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Profile Photo') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600">
            {{ __('JPG, PNG or WebP, up to 2 MB.') }}
        </p>
    </header>

    <div class="mt-6 flex items-center gap-4">
        <x-user-avatar :user="$user" class="h-20 w-20 text-2xl" />

        @if ($user->avatar_path)
            <form method="post" action="{{ route('profile-photo.destroy') }}">
                @csrf
                @method('delete')

                <x-secondary-button type="submit">{{ __('Remove photo') }}</x-secondary-button>
            </form>
        @endif
    </div>

    {{-- multipart/form-data is required, otherwise the browser sends only the file name. --}}
    <form method="post" action="{{ route('profile-photo.update') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf

        <div>
            <x-input-label for="photo" :value="__('New photo')" />
            <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required
                   class="mt-1 block w-full text-sm text-gray-700 file:me-4 file:rounded-md file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold hover:file:bg-gray-200" />
            <x-input-error class="mt-2" :messages="$errors->updatePhoto->get('photo')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Upload') }}</x-primary-button>

            @if (in_array(session('status'), ['photo-updated', 'photo-removed']))
                <p class="text-sm text-gray-600">
                    {{ session('status') === 'photo-updated' ? __('Photo updated.') : __('Photo removed.') }}
                </p>
            @endif
        </div>
    </form>
</section>
