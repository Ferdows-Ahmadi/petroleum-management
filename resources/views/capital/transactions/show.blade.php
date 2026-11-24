@extends('layouts.app')
@section('title', $capitalTransaction->reference_code)
@section('content')
@php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT'))
<x-page-header :title="$capitalTransaction->reference_code" :subtitle="__('capital.transaction')">
    @if($canManage && $capitalTransaction->status === \App\Enums\CapitalTransactionStatus::Draft)
        <a class="btnx btn-lightx" href="{{ route('capital-transactions.edit', $capitalTransaction) }}">{{ __('common.edit') }}</a>
    @endif
    @if($canManage && $capitalTransaction->status->awaitsConfirmation())
        <form method="POST" action="{{ route('capital-transactions.confirm', $capitalTransaction) }}" data-confirm="{{ __('capital.confirm_question') }}">
            @csrf
            <button class="btnx btn-primaryx">{{ __('capital.confirm') }}</button>
        </form>
    @endif
    @if($canManage && $capitalTransaction->status === \App\Enums\CapitalTransactionStatus::PendingApproval)
        <form method="POST" action="{{ route('capital-transactions.destroy', $capitalTransaction) }}" data-confirm="{{ __('common.withdraw_question') }}">
            @csrf @method('DELETE')
            <button class="btnx btn-lightx">{{ __('common.withdraw_submission') }}</button>
        </form>
    @endif
</x-page-header>
<section class="cardx card-pad"><div class="info-grid"><div class="info-item"><div class="info-label">{{ __('capital.partner') }}</div><div class="info-value"><a href="{{ route('capital-partners.show', $capitalTransaction->partner) }}">{{ $capitalTransaction->partner->name }}</a></div></div><div class="info-item"><div class="info-label">{{ __('capital.transaction_date') }}</div><div class="info-value ltr-value">@jalali($capitalTransaction->transaction_date)</div></div><div class="info-item"><div class="info-label">{{ __('capital.transaction_type') }}</div><div class="info-value">{{ $capitalTransaction->transaction_type->label() }}</div></div><div class="info-item"><div class="info-label">{{ __('capital.amount_usd') }}</div><div class="info-value ltr-value">{{ app(\App\Support\NumberFormat::class)->money($capitalTransaction->amount_usd) }} USD</div></div><div class="info-item"><div class="info-label">{{ __('capital.financial_account') }}</div><div class="info-value">{{ $capitalTransaction->account?->name ?: '—' }}</div></div><div class="info-item"><div class="info-label">{{ __('common.status') }}</div><div class="info-value"><span class="badge {{ $capitalTransaction->status->badgeClass() }}">{{ $capitalTransaction->status->label() }}</span></div></div></div>@if($capitalTransaction->notes)<div class="notice" style="margin-top:18px">{{ $capitalTransaction->notes }}</div>@endif</section>
@endsection
