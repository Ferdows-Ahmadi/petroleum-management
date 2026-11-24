@extends('layouts.app')

@section('title', __('trades.purchase.details'))

@section('content')
    @php($canViewPrices = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER'))
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="__('trades.purchase.details')" :subtitle="$purchase->reference_code">
        @if($canManage && $purchase->status === \App\Enums\TransactionStatus::Draft)
            <a class="btnx btn-lightx" href="{{ route('purchases.edit', $purchase) }}"><i class="bi bi-pencil"></i>{{ __('common.edit') }}</a>
        @endif
        @if($canManage && $purchase->status->allowsApproval())
            <form method="POST" action="{{ route('purchases.confirm', $purchase) }}" data-confirm="{{ __('trades.confirm_question') }}">@csrf<button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2-circle"></i>{{ __('trades.confirm') }}</button></form>
        @endif
        <a class="btnx btn-lightx" href="{{ route('purchases.index') }}">{{ __('common.back') }}</a>
    </x-page-header>

    @if($purchase->status === \App\Enums\TransactionStatus::Confirmed)<div class="notice subtle-notice"><i class="bi bi-shield-check"></i><div><strong>{{ __('trades.status.CONFIRMED') }}</strong> <span>{{ __('trades.confirmed_locked') }}</span></div></div>@endif

    <div class="detail-grid">
        <div class="stack">
            <section class="cardx card-pad">
                <div class="section-head"><div><div class="section-title">{{ $purchase->reference_code }}</div><div class="card-sub">{{ $purchase->reference_number ?: __('common.not_available') }}</div></div><x-transaction-status-badge :status="$purchase->status" /></div>
                <div class="info-list">
                    <div class="info-item"><div class="info-label">{{ __('trades.purchase.date') }}</div><div class="info-value ltr-value">@jalali($purchase->purchase_date)</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.purchase.supplier') }}</div><div class="info-value">{{ $purchase->supplier->name }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('trades.currency') }}</div><div class="info-value">{{ $purchase->currency->value }}</div></div>
                    <div class="info-item"><div class="info-label">{{ __('common.notes') }}</div><div class="info-value">{{ $purchase->notes ?: __('common.not_available') }}</div></div>
                </div>
            </section>

            <section class="cardx card-pad">
                <div class="section-title">{{ __('trades.items') }}</div>
                <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('trades.product') }}</th><th>{{ __('trades.warehouse') }}</th><th>{{ __('trades.quantity_kg') }}</th><th>{{ __('trades.rate_per_ton') }}</th><th>{{ __('trades.amount') }}</th><th>{{ __('inventory.movements') }}</th></tr></thead><tbody>
                @foreach($purchase->items as $item)<tr><td>{{ $item->product->name }}</td><td>{{ $item->warehouse->name }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($item->quantity_kg) }} kg</td><td class="ltr-value">{{ $canViewPrices ? (app(\App\Support\NumberFormat::class)->rate($item->rate_per_ton)) : '—' }} {{ $purchase->currency->value }}/ton</td><td class="ltr-value">{{ $canViewPrices ? (app(\App\Support\NumberFormat::class)->money($item->amount)) : '—' }} {{ $purchase->currency->value }}</td><td>@if($item->inventoryMovement)<span class="badge badge-green">+{{ app(\App\Support\NumberFormat::class)->weight($item->inventoryMovement->quantity_kg) }} kg</span>@else<span class="badge badge-gray">{{ $purchase->status->label() }}</span>@endif</td></tr>@endforeach
                </tbody></table></div>
            </section>
        </div>

        <aside class="stack">
            <section class="cardx card-pad"><div class="card-title">{{ __('trades.total_amount') }}</div><div class="calc-total ltr-value">{{ $canViewPrices ? (app(\App\Support\NumberFormat::class)->money($purchase->total_amount)) : '—' }} {{ $purchase->currency->value }}</div><div class="calc-line"><span>{{ __('trades.quantity_kg') }}</span><strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($purchase->total_quantity_kg) }} kg</strong></div></section>
            @if($canViewPrices && $purchase->ticket_image_path)
                <section class="cardx card-pad"><div class="card-title">{{ __('weighings.purchase_ticket_image') }}</div><a class="btnx btn-lightx w-100" href="{{ route('purchases.ticket', $purchase) }}" target="_blank" rel="noopener"><i class="bi bi-image"></i>{{ __('weighings.view_purchase_ticket') }}</a></section>
            @endif
            @if($canManage && $purchase->status->allowsApproval())
                <section class="cardx card-pad action-stack">
                    <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" data-confirm="{{ __('trades.cancel_question') }}">@csrf<button class="btnx btn-lightx w-100" type="submit">{{ __('trades.cancel_entry') }}</button></form>
                    @if($purchase->status === \App\Enums\TransactionStatus::Draft)<form method="POST" action="{{ route('purchases.destroy', $purchase) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="btnx btn-dangerx w-100" type="submit">{{ __('common.delete') }}</button></form>@endif
                </section>
            @endif
        </aside>
    </div>
@endsection
