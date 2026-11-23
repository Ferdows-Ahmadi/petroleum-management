@props([
    'name',
    'label',
    'role',
    'parties' => collect(),
    'selectedId' => null,
    'selectedName' => null,
    'required' => false,
    'restoreInput' => true,
])
@php
    $inputId = $attributes->get('id', $name.'_query');
    $textName = str_ends_with($name, '_id') ? substr($name, 0, -3).'_name' : $name.'_name';
    $selected = $selectedId ? $parties->firstWhere('id', (int) $selectedId) : null;
    $queryValue = $selectedName ?? $selected?->name ?? '';
    $idValue = $selectedId ?? $selected?->id;
    if ($restoreInput) {
        $queryValue = old($textName, $queryValue);
        $idValue = old($name, $idValue);
    }
@endphp
<div {{ $attributes->except('id')->class(['field', 'party-combobox']) }} data-party-combobox data-role="{{ $role }}" data-endpoint="{{ route('inline-parties.store') }}">
    <label for="{{ $inputId }}">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" value="{{ $idValue }}" data-party-id>
    <input id="{{ $inputId }}" class="control" value="{{ $queryValue }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $inputId }}_menu" aria-describedby="{{ $inputId }}_status" @required($required) data-party-query>
    <div class="party-combobox-menu" id="{{ $inputId }}_menu" role="listbox" aria-label="{{ $label }}" popover="manual" hidden data-party-menu>
        @foreach($parties as $party)
            <button id="{{ $inputId }}_option_{{ $party->id }}" class="party-option" type="button" role="option" tabindex="-1" aria-selected="false" data-party-option data-id="{{ $party->id }}" data-name="{{ $party->name }}" data-search="{{ $party->name.' '.$party->phone.' '.$party->reference_code }}">
                <span>{{ $party->name }}</span><small>{{ implode(' · ', array_filter([$party->reference_code, $party->phone])) }}</small>
            </button>
        @endforeach
        <div class="party-empty" data-party-empty hidden>شخصی با این مشخصات پیدا نشد.</div>
        <button id="{{ $inputId }}_add" class="party-create" type="button" role="option" tabindex="-1" aria-selected="false" data-party-create hidden><span></span><i class="bi bi-plus-lg" aria-hidden="true"></i></button>
    </div>
    <div class="combobox-status" id="{{ $inputId }}_status" aria-live="polite" data-party-status></div>
    @if($restoreInput)
        <x-field-error :name="$name" />
        <x-field-error :name="$textName" />
    @endif
</div>
