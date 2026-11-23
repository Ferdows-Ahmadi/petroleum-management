@extends('layouts.app')

@section('title', __('finance.transaction_details'))

@section('content')
    @php
        $mode = $financialTransaction->transaction_type === \App\Enums\FinancialTransactionType::Expense ? 'expenses' : 'payments';
        $canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT');
    @endphp
    <x-page-header :title="__('finance.transaction_details')" :subtitle="$financialTransaction->reference_code">
        @if($canManage && $financialTransaction->status === \App\Enums\FinancialTransactionStatus::Draft)<a class="btnx btn-lightx" href="{{ route($mode.'.edit', $financialTransaction) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a><form method="POST" action="{{ route($mode.'.confirm', $financialTransaction) }}" data-confirm="{{ __('finance.confirm_question') }}">@csrf<button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i>{{ __('finance.confirm') }}</button></form>@endif
        <a class="btnx btn-lightx" href="{{ route($mode.'.index') }}">{{ __('common.back') }}</a>
    </x-page-header>
    @if($financialTransaction->status === \App\Enums\FinancialTransactionStatus::Confirmed)<div class="notice subtle-notice"><i class="bi bi-shield-check"></i><div><strong>{{ __('finance.status.CONFIRMED') }}</strong>{{ __('finance.confirmed_notice') }}</div></div>@endif
    <div class="detail-grid"><section class="cardx card-pad"><div class="info-list">
        <div class="info-item"><div class="info-label">{{ __('finance.transaction_date') }}</div><div class="info-value ltr-value">@jalali($financialTransaction->transaction_date)</div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.transaction_type') }}</div><div class="info-value">{{ $financialTransaction->transaction_type->label() }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.financial_account') }}</div><div class="info-value"><a href="{{ route('financial-accounts.show', $financialTransaction->account) }}">{{ $financialTransaction->account->name }}</a></div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.partner') }}</div><div class="info-value">{{ $financialTransaction->partner?->name ?: ($financialTransaction->payee ?: __('common.not_available')) }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.native_amount') }}</div><div class="info-value ltr-value">{{ app(\App\Support\NumberFormat::class)->money($financialTransaction->amount) }} {{ $financialTransaction->currency->value }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.amount_usd') }}</div><div class="info-value ltr-value">{{ app(\App\Support\NumberFormat::class)->money($financialTransaction->amount_usd) }} USD</div></div>
        <div class="info-item"><div class="info-label">{{ __('finance.exchange_rate') }}</div><div class="info-value ltr-value">{{ $financialTransaction->exchange_rate_to_usd ?: __('common.not_available') }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('common.reference') }}</div><div class="info-value">{{ $financialTransaction->reference ?: __('common.not_available') }}</div></div>
        @if($financialTransaction->expense_category)<div class="info-item"><div class="info-label">{{ __('finance.expense_category') }}</div><div class="info-value">{{ $financialTransaction->expense_category->label() }}</div></div>@endif
        <div class="info-item"><div class="info-label">{{ __('finance.description') }}</div><div class="info-value">{{ $financialTransaction->description ?: __('common.not_available') }}</div></div>
    </div></section><aside><section class="cardx card-pad"><div class="card-title">{{ __('common.status') }}</div><div style="margin-top:12px"><span class="badge {{ $financialTransaction->status === \App\Enums\FinancialTransactionStatus::Confirmed ? 'badge-green' : 'badge-amber' }}">{{ $financialTransaction->status->label() }}</span></div></section>@if($canManage && $financialTransaction->status === \App\Enums\FinancialTransactionStatus::Draft)<section class="cardx card-pad action-stack" style="margin-top:16px"><form method="POST" action="{{ route($mode.'.destroy', $financialTransaction) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="btnx btn-dangerx w-100">{{ __('common.delete') }}</button></form></section>@endif</aside></div>
@endsection
