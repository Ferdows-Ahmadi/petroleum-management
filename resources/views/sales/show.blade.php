@extends('layouts.app')

@section('title', __('trades.sale.details'))

@section('content')
    @php
        $canViewPrices = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');
    @endphp
    @php
        $canManageSale = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER');
        $canManageWeighing = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'TRANSPORT_OFFICER');
        $hasAnyWeighing = $sale->items->contains(fn ($item) => $item->weighingRecord !== null);
        $allWeighingsFinalized = $sale->items->isNotEmpty() && $sale->items->every(fn ($item) => $item->weighingRecord?->status === \App\Enums\WeighingStatus::Finalized && $item->final_quantity_kg !== null);
    @endphp
    <x-page-header :title="__('trades.sale.details')" :subtitle="$sale->reference_code">
        @if($canManageSale && $sale->status === \App\Enums\TransactionStatus::Draft && !$hasAnyWeighing)
            <a class="btnx btn-lightx" href="{{ route('sales.edit', $sale) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a>
        @endif
        @if($canManageSale && $sale->status === \App\Enums\TransactionStatus::Draft && $allWeighingsFinalized)
            <form method="POST" action="{{ route('sales.confirm', $sale) }}" data-confirm="{{ __('trades.confirm_question') }}">@csrf<button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i>{{ __('trades.confirm') }}</button></form>
        @endif
        <a class="btnx btn-lightx" href="{{ route('sales.index') }}">{{ __('common.back') }}</a>
    </x-page-header>

    @if($sale->status === \App\Enums\TransactionStatus::Draft && !$allWeighingsFinalized)
        <div class="notice"><i class="bi bi-exclamation-circle"></i><div><strong>{{ __('trades.pending_weighing') }}</strong>{{ __('trades.confirmation_blocked_by_weighing') }}</div></div>
    @endif
    @if($sale->status === \App\Enums\TransactionStatus::Confirmed)<div class="notice subtle-notice"><i class="bi bi-shield-check"></i><div><strong>{{ __('trades.status.CONFIRMED') }}</strong>{{ __('trades.confirmed_locked') }}</div></div>@endif

    <div class="detail-grid">
        <div class="stack">
            <section class="cardx card-pad">
                <div class="section-head"><div><div class="section-title">{{ $sale->reference_code }}</div><div class="card-sub">{{ $sale->reference_number ?: __('common.not_available') }}</div></div><x-transaction-status-badge :status="$sale->status" /></div>
                <div class="info-list">
                    <div class="info-item"><div class="info-label">{{ __('trades.sale.date') }}</div><div class="info-value ltr-value">@jalali($sale->sale_date)</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.sale.customer') }}</div><div class="info-value">{{ $sale->customer->name }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.currency') }}</div><div class="info-value">{{ $sale->currency->value }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('common.notes') }}</div><div class="info-value">{{ $sale->notes ?: __('common.not_available') }}</div></div>
                </div>
            </section>

            <section class="cardx card-pad">
                <div class="section-title">{{ __('trades.items') }}</div>
                <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('trades.product') }}</th><th>{{ __('trades.warehouse') }}</th><th>{{ __('trades.requested_quantity_kg') }}</th><th>{{ __('trades.final_quantity_kg') }}</th><th>{{ __('trades.rate_per_ton') }}</th><th>{{ __('trades.amount') }}</th><th>{{ __('weighings.title') }}</th><th>{{ __('inventory.movements') }}</th></tr></thead><tbody>
                @foreach($sale->items as $item)
                    <tr>
                        <td>{{ $item->product->name }}</td>
                        <td>{{ $item->warehouse->name }}</td>
                        <td class="ltr-value">{{ $item->requested_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($item->requested_quantity_kg).' kg' : __('common.not_available') }}</td>
                        <td class="ltr-value">{{ $item->final_quantity_kg !== null ? app(\App\Support\NumberFormat::class)->weight($item->final_quantity_kg).' kg' : __('trades.pending_weighing') }}</td>
                        <td class="ltr-value">{{ $canViewPrices ? (app(\App\Support\NumberFormat::class)->rate($item->rate_per_ton)) : '—' }} {{ $sale->currency->value }}/ton</td>
                        <td class="ltr-value">{{ $canViewPrices ? ($item->final_quantity_kg !== null ? app(\App\Support\NumberFormat::class)->money($item->amount).' '.$sale->currency->value : __('trades.pending_weighing')) : '—' }}</td>
                        <td>
                            @if($item->weighingRecord)
                                <a class="badge {{ $item->weighingRecord->status === \App\Enums\WeighingStatus::Finalized ? 'badge-green' : 'badge-amber' }}" href="{{ route('weighings.show', $item->weighingRecord) }}">{{ $item->weighingRecord->status->label() }}</a>
                                @if($item->weighingRecord->ticket_image_path && auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER', 'TRANSPORT_OFFICER'))<a class="section-link" href="{{ route('weighings.ticket', $item->weighingRecord) }}" target="_blank" rel="noopener">{{ __('weighings.view_ticket') }}</a>@endif
                            @elseif($canManageWeighing && $sale->status === \App\Enums\TransactionStatus::Draft)
                                <a href="{{ route('weighings.create', ['sale_item_id' => $item->id]) }}">{{ __('weighings.create') }}</a>
                            @else
                                <span class="badge badge-gray">{{ __('weighings.pending') }}</span>
                            @endif
                        </td>
                        <td>@if($item->inventoryMovement)<span class="badge badge-red">−{{ app(\App\Support\NumberFormat::class)->weight($item->inventoryMovement->quantity_kg) }} kg</span>@else<span class="badge badge-gray">{{ __('trades.status.DRAFT') }}</span>@endif</td>
                    </tr>
                @endforeach
                </tbody></table></div>
            </section>
        </div>

        <aside class="stack">
            <section class="cardx card-pad"><div class="card-title">{{ __('trades.total_amount') }}</div><div class="calc-total ltr-value">{{ $canViewPrices ? ($allWeighingsFinalized ? app(\App\Support\NumberFormat::class)->money($sale->total_amount).' '.$sale->currency->value : __('trades.pending_weighing')) : '—' }}</div><div class="calc-line"><span>{{ __('trades.final_quantity_kg') }}</span><strong class="ltr-value">{{ $allWeighingsFinalized ? app(\App\Support\NumberFormat::class)->weight($sale->total_final_quantity_kg).' kg' : __('trades.pending_weighing') }}</strong></div></section>
            @if($canManageSale && $sale->status === \App\Enums\TransactionStatus::Draft)
                <section class="cardx card-pad action-stack">
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}" data-confirm="{{ __('trades.cancel_question') }}">@csrf<button class="btnx btn-lightx w-100" type="submit">{{ __('trades.cancel_draft') }}</button></form>
                    @if(!$hasAnyWeighing)<form method="POST" action="{{ route('sales.destroy', $sale) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="btnx btn-dangerx w-100" type="submit">{{ __('common.delete') }}</button></form>@endif
                </section>
            @endif
        </aside>
    </div>
@endsection
