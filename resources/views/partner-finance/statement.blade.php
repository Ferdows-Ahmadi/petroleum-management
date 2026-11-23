@extends('layouts.app')

@section('title', __('finance.statement'))

@section('content')
    @php
        $canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT');
        $canViewCoreFinance = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT');
        $balance = $statement->last()['running_balance'] ?? '0.00';
    @endphp
    <x-page-header :title="__('finance.statement')" :subtitle="__('finance.statement_subtitle')">
        @if($selectedPartner && $canManage && !$openingBalanceLocked)<a class="btnx btn-lightx" href="{{ route('partners.opening-balance.edit', $selectedPartner) }}"><i class="bi bi-arrow-bar-right"></i>{{ __('finance.opening_balance') }}</a>@endif
        @if($selectedPartner && $canManage)<a class="btnx btn-primaryx" href="{{ route('office.accounts', ['partner_id' => $selectedPartner->id]) }}"><i class="bi bi-cash-stack"></i>{{ __('finance.create_payment') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">
        <form class="filters" method="GET" action="{{ route('partners.statements') }}"><select class="control grow" name="partner_id">@foreach($partners as $partner)<option value="{{ $partner->id }}" @selected($selectedPartner?->id === $partner->id)>{{ $partner->name }}</option>@endforeach</select><button class="btnx btn-primaryx">{{ __('common.view') }}</button></form>
        @if(!$selectedPartner)<div class="empty">{{ __('common.no_records') }}</div>@else
            <div class="stats-row" style="margin:14px 0;display:grid;grid-template-columns:repeat(3,1fr);gap:12px"><div class="mini-stat"><div class="mini-label">{{ __('finance.partner') }}</div><div class="mini-value">{{ $selectedPartner->name }}</div></div><div class="mini-stat"><div class="mini-label">{{ __('finance.running_balance') }}</div><div class="mini-value ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($balance) }} USD</div></div><div class="mini-stat"><div class="mini-label">{{ __('common.status') }}</div><div class="mini-value">{{ bccomp($balance, '0', 2) > 0 ? __('finance.partner_owes_company') : (bccomp($balance, '0', 2) < 0 ? __('finance.company_owes_partner') : __('finance.settled')) }}</div></div></div>
            <div class="notice subtle-notice"><i class="bi bi-info-circle"></i><div><strong>{{ __('finance.running_balance') }}</strong>{{ __('finance.partner_formula') }}</div></div>
            @if($statement->isEmpty())<div class="empty compact">{{ __('common.no_records') }}</div>@else<div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('finance.transaction_date') }}</th><th>{{ __('finance.transaction_type') }}</th><th>{{ __('common.reference') }}</th><th>{{ __('finance.debit') }}</th><th>{{ __('finance.credit') }}</th><th>{{ __('finance.running_balance') }}</th></tr></thead><tbody>
            @foreach($statement as $entry)<tr><td class="ltr-value">{{ app(\App\Support\JalaliDate::class)->format($entry['date']) }}</td><td>{{ __('finance.ledger_types.'.$entry['type']) }}</td><td>@if($entry['type'] === 'SALE')<a href="{{ route('sales.show', $entry['source']) }}">{{ $entry['reference'] }}</a>@elseif($entry['type'] === 'PURCHASE')<a href="{{ route('purchases.show', $entry['source']) }}">{{ $entry['reference'] }}</a>@elseif(in_array($entry['type'], ['DEAL_SOURCE', 'DEAL_DESTINATION'], true))<a href="{{ route('deals.show', $entry['source']) }}">{{ $entry['reference'] }}</a>@elseif(in_array($entry['type'], ['CUSTOMER_RECEIPT', 'SUPPLIER_PAYMENT'], true) && $canViewCoreFinance)<a href="{{ route('money-movements.show', $entry['source']) }}">{{ $entry['reference'] }}</a>@elseif(in_array($entry['type'], ['PAYMENT_RECEIVED', 'PAYMENT_PAID'], true) && $canViewCoreFinance)<a href="{{ route('payments.show', $entry['source']) }}">{{ $entry['reference'] }}</a>@else{{ $entry['reference'] }}@endif</td><td class="ltr-value">{{ $entry['debit'] ? app(\App\Support\NumberFormat::class)->money($entry['debit']).' USD' : '—' }}</td><td class="ltr-value">{{ $entry['credit'] ? app(\App\Support\NumberFormat::class)->money($entry['credit']).' USD' : '—' }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->absoluteMoney($entry['running_balance']) }} USD</strong><div class="td-sub">{{ bccomp($entry['running_balance'], '0', 2) > 0 ? __('finance.partner_owes_company') : (bccomp($entry['running_balance'], '0', 2) < 0 ? __('finance.company_owes_partner') : __('finance.settled')) }}</div></td></tr>@endforeach
            </tbody></table></div>@endif
        @endif
    </section>
@endsection
