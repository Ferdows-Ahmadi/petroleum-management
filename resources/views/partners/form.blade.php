@extends('layouts.app')

@php($editing = $partner->exists)
@section('title', $editing ? __('partners.edit_title') : __('partners.create_title'))

@section('content')
    <x-page-header :title="$editing ? __('partners.edit_title') : __('partners.create_title')" :subtitle="__('partners.form_subtitle')" />
    <form class="cardx card-pad" method="POST" action="{{ $editing ? route('partners.update', $partner) : route('partners.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">{{ __('partners.name') }}</label><input class="control" id="name" name="name" value="{{ old('name', $partner->name) }}" placeholder="{{ __('partners.name_placeholder') }}" required><x-field-error name="name" /></div>
            <div class="field"><label for="phone">{{ __('common.phone') }}</label><input class="control ltr-value" id="phone" name="phone" value="{{ old('phone', $partner->phone) }}" placeholder="{{ __('partners.phone_placeholder') }}"><x-field-error name="phone" /></div>
            <div class="field"><label>{{ __('partners.relationship') }}</label><div class="role-options">@foreach($partnerRoles as $role)<label class="role-option"><input type="checkbox" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, old('roles', $selectedRoles), true))><span>{{ $role->label() }}</span></label>@endforeach</div><x-field-error name="roles" /><x-field-error name="roles.*" /></div>
            <div class="field"><label for="is_active">{{ __('common.status') }}</label><select class="control" id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', $partner->exists ? (int) $partner->is_active : 1) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', $partner->exists ? (int) $partner->is_active : 1) === '0')>{{ __('common.inactive') }}</option></select><x-field-error name="is_active" /></div>
            <div class="field span-2"><label for="address">{{ __('common.address') }}</label><input class="control" id="address" name="address" value="{{ old('address', $partner->address) }}" placeholder="{{ __('partners.address_placeholder') }}"><x-field-error name="address" /></div>
            <div class="field span-2"><label for="notes">{{ __('common.notes') }}</label><textarea class="control textarea" id="notes" name="notes" placeholder="{{ __('partners.notes_placeholder') }}">{{ old('notes', $partner->notes) }}</textarea><x-field-error name="notes" /></div>
        </div>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2"></i>{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('partners.index') }}">{{ __('common.cancel') }}</a></div>
    </form>
@endsection
