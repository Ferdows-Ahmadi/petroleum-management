@props([
    'name',
    'label',
    'role',
    'parties' => collect(),
    'selectedId' => null,
    'selectedName' => null,
    'required' => false,
])
@php
    $inputId = $attributes->get('id', $name.'_query');
    $textName = str_ends_with($name, '_id') ? substr($name, 0, -3).'_name' : $name.'_name';
    $selected = $selectedId ? $parties->firstWhere('id', (int) $selectedId) : null;
    $queryValue = old($textName, $selectedName ?? $selected?->name ?? '');
    $idValue = old($name, $selectedId ?? $selected?->id);
@endphp
<div class="field" data-party-combobox data-role="{{ $role }}" data-endpoint="{{ route('inline-parties.store') }}" {{ $attributes->except('id') }}>
    <label for="{{ $inputId }}">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" value="{{ $idValue }}" data-party-id>
    <input id="{{ $inputId }}" class="control" name="{{ $textName }}" value="{{ $queryValue }}" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="{{ $inputId }}_menu" @required($required) data-party-query>
    <div class="party-combobox-menu" id="{{ $inputId }}_menu" role="listbox" hidden data-party-menu>
        @foreach($parties as $party)
            <button class="party-option" type="button" role="option" data-party-option data-id="{{ $party->id }}" data-name="{{ $party->name }}" data-search="{{ mb_strtolower($party->name.' '.$party->phone.' '.$party->reference_code) }}">
                <span>{{ $party->name }}</span><small>{{ $party->phone ?: $party->reference_code }}</small>
            </button>
        @endforeach
        <button class="party-create" type="button" data-party-create hidden><span></span><i class="bi bi-plus-lg"></i></button>
    </div>
    <div class="combobox-status" aria-live="polite" data-party-status></div>
    <x-field-error :name="$name" />
    <x-field-error :name="$textName" />
</div>
