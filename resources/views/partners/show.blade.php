@extends('layouts.app')

@section('title', $partner->name)

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="$partner->name" :subtitle="__('partners.detail_subtitle')">
        @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER'))<a class="btnx btn-lightx" href="{{ route('partners.statement', $partner) }}"><i class="bi bi-journal-text"></i>{{ __('finance.statement') }}</a>@endif
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('partners.edit', $partner) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a>@endif
        <a class="btnx btn-lightx" href="{{ route('partners.index') }}">{{ __('common.back') }}</a>
    </x-page-header>
    <section class="cardx card-pad"><div class="info-list">
        <div class="info-item"><div class="info-label">{{ __('common.reference') }}</div><div class="info-value">{{ $partner->reference_code }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('common.status') }}</div><div class="info-value"><x-status-badge :active="$partner->is_active" /></div></div>
        <div class="info-item"><div class="info-label">{{ __('partners.relationship') }}</div><div class="info-value role-badges">@foreach($partner->roles as $assignment)<span class="badge {{ match($assignment->role->value) { 'CUSTOMER' => 'badge-blue', 'SUPPLIER' => 'badge-gray', 'SARAF' => 'badge-amber', default => 'badge-green' } }}">{{ $assignment->role->label() }}</span>@endforeach</div></div>
        <div class="info-item"><div class="info-label">{{ __('common.phone') }}</div><div class="info-value ltr-value">{{ $partner->phone ?: __('common.not_available') }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('common.address') }}</div><div class="info-value">{{ $partner->address ?: __('common.not_available') }}</div></div>
        <div class="info-item"><div class="info-label">{{ __('common.notes') }}</div><div class="info-value">{{ $partner->notes ?: __('common.not_available') }}</div></div>
    </div></section>
    @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER'))
        <section class="cardx card-pad" style="margin-top:16px"><div class="card-title">{{ __('finance.running_balance') }}</div><div class="calc-total ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($partnerBalance) }} USD</div><div class="card-sub">{{ bccomp($partnerBalance, '0', 2) > 0 ? __('finance.partner_owes_company') : (bccomp($partnerBalance, '0', 2) < 0 ? __('finance.company_owes_partner') : __('finance.settled')) }}</div></section>
    @endif
@endsection
