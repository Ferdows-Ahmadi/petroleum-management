@extends('layouts.app')

@php($editing = $warehouse->exists)
@section('title', $editing ? __('warehouses.edit_title') : __('warehouses.create_title'))

@section('content')
    <x-page-header :title="$editing ? __('warehouses.edit_title') : __('warehouses.create_title')" :subtitle="__('warehouses.form_subtitle')" />
    <form class="cardx card-pad" method="POST" action="{{ $editing ? route('warehouses.update', $warehouse) : route('warehouses.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">{{ __('warehouses.name') }}</label><input class="control" id="name" name="name" value="{{ old('name', $warehouse->name) }}" placeholder="{{ __('warehouses.name_placeholder') }}" required><x-field-error name="name" /></div>
            <div class="field"><label for="is_active">{{ __('common.status') }}</label><select class="control" id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', $warehouse->exists ? (int) $warehouse->is_active : 1) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', $warehouse->exists ? (int) $warehouse->is_active : 1) === '0')>{{ __('common.inactive') }}</option></select><x-field-error name="is_active" /></div>
            <div class="field span-2"><label for="location">{{ __('warehouses.location') }}</label><input class="control" id="location" name="location" value="{{ old('location', $warehouse->location) }}" placeholder="{{ __('warehouses.location_placeholder') }}"><x-field-error name="location" /></div>
            <div class="field span-2"><label for="description">{{ __('warehouses.description') }}</label><textarea class="control textarea" id="description" name="description" placeholder="{{ __('warehouses.description_placeholder') }}">{{ old('description', $warehouse->description) }}</textarea><x-field-error name="description" /></div>
        </div>
        <div class="notice subtle-notice"><i class="bi bi-calculator"></i><div><strong>{{ __('warehouses.stock') }}</strong> <span>{{ __('warehouses.stock_rule') }}</span></div></div>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2"></i>{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('warehouses.index') }}">{{ __('common.cancel') }}</a></div>
    </form>
@endsection
