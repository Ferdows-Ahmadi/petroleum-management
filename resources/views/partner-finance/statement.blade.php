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
        <form class="filters" method="GET" action="{{ route('partners.statements') }}"><select class="control grow" aria-label="{{ __('finance.partner') }}" name="partner_id">@foreach($partners as $partner)<option value="{{ $partner->id }}" @selected($selectedPartner?->id === $partner->id)>{{ $partner->name }} · {{ $partner->reference_code }}</option>@endforeach</select><button class="btnx btn-primaryx">{{ __('common.view') }}</button></form>
        @if(!$selectedPartner)<div class="empty">{{ __('common.no_records') }}</div>@else
            <div class="form-grid-3" style="margin-block:14px"><div class="mini-stat"><div class="mini-label">{{ __('finance.partner') }}</div><div class="mini-value">{{ $selectedPartner->name }}</div></div><div class="mini-stat"><div class="mini-label">{{ __('finance.running_balance') }}</div><div class="mini-value ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($balance) }} USD</div></div><div class="mini-stat"><div class="mini-label">{{ __('common.status') }}</div><div class="mini-value">{{ bccomp($balance, '0', 2) > 0 ? __('finance.partner_owes_company') : (bccomp($balance, '0', 2) < 0 ? __('finance.company_owes_partner') : __('finance.settled')) }}</div></div></div>
            <div class="notice subtle-notice"><i class="bi bi-info-circle"></i><div><strong>{{ __('finance.running_balance') }}</strong>{{ __('finance.partner_formula') }}</div></div>
            <div data-ledger>
                @include('partner-finance._ledger', ['showTradeDetails' => false])
            </div>
        @endif
    </section>
@endsection
