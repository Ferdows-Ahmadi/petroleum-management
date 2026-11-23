@extends('layouts.app')

@section('title', __('weighings.details'))

@section('content')
    @php
        $canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'TRANSPORT_OFFICER');
        $canViewTicket = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER', 'TRANSPORT_OFFICER');
        $canViewSale = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER', 'WAREHOUSE_OFFICER');
    @endphp
    <x-page-header :title="__('weighings.details')" :subtitle="$weighingRecord->reference_code">
        @if($canManage && !$weighingRecord->deal_destination_id && $weighingRecord->status === \App\Enums\WeighingStatus::Draft)
            <a class="btnx btn-lightx" href="{{ route('weighings.edit', $weighingRecord) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a>
            <form method="POST" action="{{ route('weighings.finalize', $weighingRecord) }}" data-confirm="{{ __('weighings.finalize_question') }}">@csrf<button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i>{{ __('weighings.finalize') }}</button></form>
        @endif
        <a class="btnx btn-lightx" href="{{ route('weighings.index') }}">{{ __('common.back') }}</a>
    </x-page-header>

    @if($weighingRecord->status === \App\Enums\WeighingStatus::Finalized)<div class="notice subtle-notice"><i class="bi bi-shield-check"></i><div><strong>{{ __('weighings.status.FINALIZED') }}</strong>{{ __('weighings.immutable_notice') }}</div></div>@endif

    <div class="detail-grid">
        <div class="stack">
            <section class="cardx card-pad">
                <div class="section-head"><div><div class="section-title">{{ $weighingRecord->reference_code }}</div><div class="card-sub">{{ $weighingRecord->station_name ?: __('common.not_available') }}</div></div><span class="badge {{ $weighingRecord->status === \App\Enums\WeighingStatus::Finalized ? 'badge-green' : 'badge-amber' }}"><span class="dot"></span>{{ $weighingRecord->status->label() }}</span></div>
                <div class="info-list">
                    <div class="info-item"><div class="info-label">{{ __('weighings.weighing_at') }}</div><div class="info-value ltr-value">@jalaliDateTime($weighingRecord->weighing_at)</div></div>
                    <div class="info-item"><div class="info-label">{{ __('weighings.vehicle_number') }}</div><div class="info-value">{{ $weighingRecord->vehicle_number ?: __('common.not_available') }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('weighings.driver_name') }}</div><div class="info-value">{{ $weighingRecord->driver_name ?: __('common.not_available') }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('common.notes') }}</div><div class="info-value">{{ $weighingRecord->notes ?: __('common.not_available') }}</div></div>
                </div>
            </section>

            <section class="cardx card-pad">
                <div class="section-title">{{ __('weighings.sale_item') }}</div>
                <div class="info-list">
                    @if($weighingRecord->dealDestination)
                    <div class="info-item"><div class="info-label">{{ __('common.reference') }}</div><div class="info-value">{{ $weighingRecord->dealDestination->deal->reference_code }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('deals.customer') }}</div><div class="info-value">{{ $weighingRecord->dealDestination->customer?->name ?: __('common.not_available') }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.product') }}</div><div class="info-value">{{ $weighingRecord->dealDestination->deal->product?->name ?: __('common.not_available') }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('deals.final_quantity') }}</div><div class="info-value ltr-value">{{ $weighingRecord->dealDestination->final_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($weighingRecord->dealDestination->final_quantity_kg).' kg' : __('weighings.pending') }}</div></div>
                    @else
                    <div class="info-item"><div class="info-label">{{ __('weighings.sale_reference') }}</div><div class="info-value">{{ $weighingRecord->saleItem->sale->reference_code }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.sale.customer') }}</div><div class="info-value">{{ $weighingRecord->saleItem->sale->customer->name }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.product') }}</div><div class="info-value">{{ $weighingRecord->saleItem->product->name }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.warehouse') }}</div><div class="info-value">{{ $weighingRecord->saleItem->warehouse->name }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.requested_quantity_kg') }}</div><div class="info-value ltr-value">{{ $weighingRecord->saleItem->requested_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($weighingRecord->saleItem->requested_quantity_kg).' kg' : __('common.not_available') }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.final_quantity_kg') }}</div><div class="info-value ltr-value">{{ $weighingRecord->saleItem->final_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($weighingRecord->saleItem->final_quantity_kg).' kg' : __('weighings.pending') }}</div></div>
                    @endif
                </div>
                @if($weighingRecord->dealDestination)<a class="section-link" href="{{ route('deals.show', $weighingRecord->dealDestination->deal) }}">{{ __('common.view') }} {{ __('deals.details') }}</a>@elseif($canViewSale)<a class="section-link" href="{{ route('sales.show', $weighingRecord->saleItem->sale) }}">{{ __('common.view') }} {{ __('weighings.sale_reference') }}</a>@endif
            </section>
        </div>

        <aside class="stack">
            <section class="cardx card-pad">
                <div class="card-title">{{ __('weighings.calculation') }}</div>
                <div class="calc-line"><span>{{ __('weighings.weighing_mode') }}</span><strong>{{ $weighingRecord->weighing_mode->label() }}</strong></div>
                @if($weighingRecord->weighing_mode === \App\Enums\WeighingMode::TwoStage)
                    <div class="calc-line"><span>{{ __('weighings.first_weight_kg') }}</span><strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($weighingRecord->first_weight_kg) }} kg</strong></div>
                    <div class="calc-line"><span>{{ __('weighings.second_weight_kg') }}</span><strong class="ltr-value">{{ $weighingRecord->second_weight_kg !== null ? app(\App\Support\NumberFormat::class)->weight($weighingRecord->second_weight_kg).' kg' : '—' }}</strong></div>
                @endif
                <div class="calc-line"><span>{{ __('weighings.net_weight_kg') }}</span><strong class="calc-total ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($weighingRecord->net_weight_kg) }} kg</strong></div>
            </section>
            @if($weighingRecord->ticket_image_path)
                <section class="cardx card-pad"><div class="card-title">{{ __('weighings.ticket_image') }}</div><div class="card-sub">{{ __('weighings.private_ticket_notice') }}</div>@if($canViewTicket)<a class="btnx btn-lightx w-100" href="{{ route('weighings.ticket', $weighingRecord) }}" target="_blank" rel="noopener"><i class="bi bi-image"></i>{{ __('weighings.view_ticket') }}</a>@endif</section>
            @endif
        </aside>
    </div>
@endsection
