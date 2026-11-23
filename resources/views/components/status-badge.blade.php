@props(['active'])
<span class="badge {{ $active ? 'badge-green' : 'badge-gray' }}">
    <span class="dot"></span>{{ $active ? __('common.active') : __('common.inactive') }}
</span>
