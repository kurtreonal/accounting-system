@props(['user'])

<span {{ $attributes->merge(['class' => 'grid shrink-0 place-items-center overflow-hidden rounded-full']) }} data-avatar-user-id="{{ $user['id'] ?? '' }}">
    @if (! empty($user['avatar_data_url']))
        <img src="{{ $user['avatar_data_url'] }}" alt="" class="size-full object-cover" data-avatar-image>
    @else
        <i class="fa-solid fa-user" aria-hidden="true" data-avatar-default></i>
    @endif
</span>
