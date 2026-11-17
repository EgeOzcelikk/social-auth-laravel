@props(['user'])

{{-- Shows the profile photo, or the first letter of the name if there is none. --}}
@if ($url = $user->profilePhotoUrl())
    <img src="{{ $url }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'rounded-full object-cover']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700']) }}>
        {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
    </span>
@endif
