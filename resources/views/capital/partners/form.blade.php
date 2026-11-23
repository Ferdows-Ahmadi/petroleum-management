@extends('layouts.app')
@section('title', $capitalPartner->exists ? __('capital.edit_partner') : __('capital.new_partner'))
@section('content')
<x-page-header :title="$capitalPartner->exists ? __('capital.edit_partner') : __('capital.new_partner')" />
<form method="POST" action="{{ $capitalPartner->exists ? route('capital-partners.update', $capitalPartner) : route('capital-partners.store') }}">@csrf @if($capitalPartner->exists)@method('PUT')@endif
<section class="cardx card-pad"><div class="form-grid"><div class="field"><label>{{ __('common.name') }}</label><input class="control" name="name" value="{{ old('name', $capitalPartner->name) }}" required><x-field-error name="name" /></div><div class="field"><label>{{ __('capital.phone') }}</label><input class="control ltr-value" name="phone" value="{{ old('phone', $capitalPartner->phone) }}"><x-field-error name="phone" /></div><div class="field span-2"><label>{{ __('common.notes') }}</label><textarea class="control textarea" name="notes">{{ old('notes', $capitalPartner->notes) }}</textarea></div><label class="role-option"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $capitalPartner->is_active))><span>{{ __('common.active') }}</span></label></div></section>
<div class="form-actions"><button class="btnx btn-primaryx">{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('capital-partners.index') }}">{{ __('common.cancel') }}</a></div></form>
@endsection
