@extends('layouts.app')

@section('title', $warehouse->name)

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'WAREHOUSE_OFFICER'))
    <x-page-header :title="$warehouse->name" :subtitle="__('warehouses.detail_subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('warehouses.edit', $warehouse) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a>@endif
        <a class="btnx btn-lightx" href="{{ route('warehouses.index') }}">{{ __('common.back') }}</a>
    </x-page-header>
    <div class="detail-grid">
        <section class="cardx card-pad"><div class="info-list">
            <div class="info-item"><div class="info-label">{{ __('common.reference') }}</div><div class="info-value">{{ $warehouse->reference_code }}</div></div>
            <div class="info-item"><div class="info-label">{{ __('common.status') }}</div><div class="info-value"><x-status-badge :active="$warehouse->is_active" /></div></div>
            <div class="info-item"><div class="info-label">{{ __('warehouses.location') }}</div><div class="info-value">{{ $warehouse->location ?: __('common.not_available') }}</div></div>
            <div class="info-item"><div class="info-label">{{ __('warehouses.description') }}</div><div class="info-value">{{ $warehouse->description ?: __('common.not_available') }}</div></div>
        </div></section>
        <section class="cardx card-pad"><div class="section-title">{{ __('warehouses.stock') }}</div><div class="card-sub">{{ __('inventory.calculated_notice') }}</div><div class="stock-list">@foreach($products as $product)<div class="calc-line"><span>{{ $product->name }}</span><strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($product->calculated_stock_kg) }} kg</strong></div>@endforeach</div></section>
    </div>
    <section class="cardx card-pad mt-3">
        <div class="section-head"><div><div class="section-title">{{ __('inventory.movements') }}</div><div class="card-sub">{{ __('inventory.movements_subtitle') }}</div></div><a href="{{ route('inventory.movements', ['warehouse_id' => $warehouse->id]) }}">{{ __('common.view') }}</a></div>
        @if($movements->isEmpty())<div class="empty compact">{{ __('common.no_records') }}</div>@else<div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('inventory.occurred_at') }}</th><th>{{ __('inventory.movement_type') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('trades.quantity_kg') }}</th><th>{{ __('inventory.source') }}</th></tr></thead><tbody>@foreach($movements as $movement)<tr><td class="ltr-value">@jalaliDateTime($movement->occurred_at)</td><td><span class="badge {{ $movement->movement_type->direction() === 1 ? 'badge-green' : 'badge-red' }}">{{ $movement->movement_type->label() }}</span></td><td>{{ $movement->product->name }}</td><td class="ltr-value">{{ $movement->movement_type->direction() === 1 ? '+' : '−' }}{{ app(\App\Support\NumberFormat::class)->weight($movement->quantity_kg) }} kg</td><td>@include('inventory._source')</td></tr>@endforeach</tbody></table></div>@endif
    </section>
@endsection
