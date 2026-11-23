@extends('layouts.app')

@section('title', __('finance.opening_balance'))

@section('content')
    <x-page-header :title="__('finance.opening_balance')" :subtitle="$partner->name" />
    <div class="notice"><i class="bi bi-info-circle"></i><div><strong>{{ __('finance.opening_balance') }}</strong>{{ __('finance.partner_formula') }}</div></div>
    <form method="POST" action="{{ route('partners.opening-balance.update', $partner) }}">@csrf @method('PUT')
        <section class="cardx card-pad"><div class="form-grid"><div class="field"><label for="effective_date">{{ __('finance.transaction_date') }}</label><input id="effective_date" class="control ltr-value" name="effective_date" value="{{ old('effective_date', app(\App\Support\JalaliDate::class)->format($openingBalance->effective_date)) }}" placeholder="1405/05/19" required><x-field-error name="effective_date" /></div><div class="field"><label for="balance_type">{{ __('finance.opening_type') }}</label><select id="balance_type" class="control" name="balance_type">@foreach($types as $type)<option value="{{ $type->value }}" @selected(old('balance_type', $openingBalance->balance_type?->value) === $type->value)>{{ $type->label() }}</option>@endforeach</select></div><div class="field"><label for="amount_usd">{{ __('finance.amount') }} (USD)</label><input id="amount_usd" class="control ltr-value" name="amount_usd" inputmode="decimal" value="{{ old('amount_usd', $openingBalance->amount_usd) }}" required><x-field-error name="amount_usd" /></div><div class="field span-2"><label for="notes">{{ __('common.notes') }}</label><textarea id="notes" class="control textarea" name="notes">{{ old('notes', $openingBalance->notes) }}</textarea></div></div></section>
        <div class="form-actions"><button class="btnx btn-primaryx"><i class="bi bi-check2"></i>{{ __('common.save') }}</button><a class="btnx btn-lightx" href="{{ route('partners.statement', $partner) }}">{{ __('common.cancel') }}</a></div>
    </form>
    @if($openingBalance->exists)<form method="POST" action="{{ route('partners.opening-balance.destroy', $partner) }}" data-confirm="{{ __('common.confirm_delete') }}" style="margin-top:12px">@csrf @method('DELETE')<button class="btnx btn-dangerx">{{ __('common.delete') }}</button></form>@endif
@endsection
