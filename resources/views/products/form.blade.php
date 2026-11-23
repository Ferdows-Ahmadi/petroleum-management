@extends('layouts.app')

@php($editing = $product->exists)
@section('title', $editing ? __('products.edit_title') : __('products.create_title'))

@section('content')
    <x-page-header :title="$editing ? __('products.edit_title') : __('products.create_title')" :subtitle="__('products.form_subtitle')" />
    <form class="cardx card-pad" method="POST" action="{{ $editing ? route('products.update', $product) : route('products.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="form-grid">
            <div class="field"><label for="name">{{ __('products.name') }}</label><input class="control" id="name" name="name" value="{{ old('name', $product->name) }}" placeholder="{{ __('products.name_placeholder') }}" required><x-field-error name="name" /></div>
            <div class="field"><label for="code">{{ __('products.code') }}</label><input class="control ltr-value" id="code" name="code" value="{{ old('code', $product->code) }}" placeholder="{{ __('products.code_placeholder') }}" required><x-field-error name="code" /></div>
            <div class="field"><label for="unit">{{ __('products.unit') }}</label><select class="control" id="unit" name="unit"><option value="kg" @selected(old('unit', $product->unit ?: 'kg') === 'kg')>kg</option></select><x-field-error name="unit" /></div>
            <div class="field"><label for="is_active">{{ __('common.status') }}</label><select class="control" id="is_active" name="is_active"><option value="1" @selected((string) old('is_active', $product->exists ? (int) $product->is_active : 1) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', $product->exists ? (int) $product->is_active : 1) === '0')>{{ __('common.inactive') }}</option></select><x-field-error name="is_active" /></div>
            <div class="field span-2"><label for="notes">{{ __('common.notes') }}</label><textarea class="control textarea" id="notes" name="notes" placeholder="{{ __('products.notes_placeholder') }}">{{ old('notes', $product->notes) }}</textarea><x-field-error name="notes" /></div>
        </div>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2"></i>{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('products.index') }}">{{ __('common.cancel') }}</a></div>
    </form>
@endsection
