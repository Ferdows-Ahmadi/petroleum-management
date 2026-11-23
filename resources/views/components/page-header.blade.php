@props(['title', 'subtitle' => null])
<div class="page-head">
    <div>
        <h1 class="page-title">{{ $title }}</h1>
        @if($subtitle)<div class="page-sub">{{ $subtitle }}</div>@endif
    </div>
    @if(trim((string) $slot) !== '')<div class="head-actions">{{ $slot }}</div>@endif
</div>
