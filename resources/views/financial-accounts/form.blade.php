@extends('layouts.app')

@section('title', $financialAccount->exists ? __('finance.account_edit') : __('finance.account_create'))

@section('content')
    <x-page-header :title="$financialAccount->exists ? __('finance.account_edit') : __('finance.account_create')" :subtitle="__('finance.accounts_subtitle')" />
    @if($errors->any())<div class="alertx alert-errorx"><i class="bi bi-exclamation-circle"></i>{{ __('common.validation_summary') }}</div>@endif
    <form method="POST" action="{{ $financialAccount->exists ? route('financial-accounts.update', $financialAccount) : route('financial-accounts.store') }}">
        @csrf @if($financialAccount->exists)@method('PUT')@endif
        <section class="cardx card-pad"><div class="form-grid">
            <div class="field"><label for="name">{{ __('finance.account_name') }}</label><input id="name" class="control" name="name" value="{{ old('name', $financialAccount->name) }}" required><x-field-error name="name" /></div>
            <div class="field"><label for="account_type">{{ __('finance.account_type') }}</label><select id="account_type" class="control" name="account_type" required>@foreach($accountTypes as $type)<option value="{{ $type->value }}" @selected(old('account_type', $financialAccount->account_type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select><x-field-error name="account_type" /></div>
            <div class="field"><label for="currency">{{ __('finance.currency') }}</label><select id="currency" class="control" name="currency" required>@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected(old('currency', $financialAccount->currency?->value) === $currency->value)>{{ $currency->value }}</option>@endforeach</select><x-field-error name="currency" /></div>
            <div class="field"><label for="is_active">{{ __('common.status') }}</label><select id="is_active" class="control" name="is_active"><option value="1" @selected((string) old('is_active', (int) $financialAccount->is_active) === '1')>{{ __('common.active') }}</option><option value="0" @selected((string) old('is_active', (int) $financialAccount->is_active) === '0')>{{ __('common.inactive') }}</option></select></div>
            <div class="field"><label for="opening_balance">{{ __('finance.opening_balance') }}</label><input id="opening_balance" class="control ltr-value" name="opening_balance" inputmode="decimal" value="{{ old('opening_balance', $financialAccount->opening_balance) }}" required><x-field-error name="opening_balance" /></div>
            <div class="field"><label for="opening_balance_date">{{ __('finance.opening_balance_date') }}</label><input id="opening_balance_date" class="control ltr-value" name="opening_balance_date" value="{{ old('opening_balance_date', app(\App\Support\JalaliDate::class)->format($financialAccount->opening_balance_date)) }}" placeholder="1405/05/19" required><x-field-error name="opening_balance_date" /></div>
            <div class="field span-2"><label for="description">{{ __('finance.description') }}</label><textarea id="description" class="control textarea" name="description">{{ old('description', $financialAccount->description) }}</textarea><x-field-error name="description" /></div>
        </div></section>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2"></i>{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('financial-accounts.index') }}">{{ __('common.cancel') }}</a></div>
    </form>
@endsection
