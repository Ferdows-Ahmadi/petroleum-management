@extends('layouts.app')

@section('title', __('navigation.storage'))

@section('content')
@php
    $canViewPrices = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');
@endphp
<x-page-header :title="__('navigation.storage')" :subtitle="__('inventory.workspace_subtitle')" />
@if($warehouses->isEmpty())
    <section class="cardx card-pad">
        <div class="section-title">هنوز ذخیره فعال ثبت نشده است</div>
        <p>ذخیره را تنها وقتی ثبت کنید که مواد شرکت واقعاً وارد یا از آن خارج می‌شود. معامله مستقیم عرضه‌کننده به مشتری به ذخیره نیاز ندارد.</p>
        @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'WAREHOUSE_OFFICER'))
            <a class="btnx btn-primaryx" href="{{ route('warehouses.create') }}">ثبت اولین ذخیره</a>
        @else
            <p>از مدیر بخواهید نام و موقعیت ذخیره مورد نیاز را در تنظیمات ثبت کند.</p>
        @endif
    </section>
@else
<section class="cardx card-pad toolbar-panel"><form class="filters" method="GET"><label class="field grow"><span>{{ __('trades.warehouse') }}</span><select class="control" name="warehouse_id">@foreach($warehouses as $item)<option value="{{ $item->id }}" @selected($warehouse?->id === $item->id)>{{ $item->name }}</option>@endforeach</select></label><label class="field grow"><span>{{ __('trades.product') }}</span><select class="control" name="product_id"><option value="">{{ __('inventory.all_products') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected($productId === $product->id)>{{ $product->name }}</option>@endforeach</select></label><button class="btnx btn-primaryx">{{ __('common.view') }}</button></form></section>
@endif

@if($warehouse)
    @php
        $totalValue = $rows->contains(fn ($row) => $row['value'] === null) ? null : $rows->reduce(fn ($sum, $row) => bcadd($sum, $row['value'], 2), '0.00');
    @endphp
    <section class="cardx card-pad">
        <div class="section-head"><div><div class="section-title">موجودی {{ $warehouse->name }}</div><div class="card-sub">{{ $warehouse->location ?: $warehouse->reference_code }} · موجودی مشتق‌شده از حرکات تأییدشده</div></div><div class="head-actions"><span class="badge badge-blue">{{ app(\App\Support\NumberFormat::class)->weight($rows->reduce(fn ($sum, $row) => bcadd($sum, $row['balance'], 3), '0.000')) }} kg</span>@if($canViewPrices && $totalValue !== null)<span class="badge badge-gray ltr-value">{{ app(\App\Support\NumberFormat::class)->money($totalValue) }} USD</span>@endif</div></div>
        <div class="table-wrap"><table class="tablex"><thead><tr><th>ذخیره</th><th>{{ __('trades.product') }}</th><th>{{ __('inventory.balance') }}</th><th>{{ __('inventory.approximate_value') }}</th><th>{{ __('inventory.storage_claim') }}</th><th>آخرین حرکت</th></tr></thead><tbody>@forelse($rows as $row)<tr><td class="td-main">{{ $warehouse->name }}</td><td>{{ $row['product']->name }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->weight($row['balance']) }} kg</strong></td><td class="ltr-value">{{ !$canViewPrices || $row['value'] === null ? '—' : app(\App\Support\NumberFormat::class)->money($row['value']).' USD' }}</td><td class="ltr-value">{{ $canViewPrices ? app(\App\Support\NumberFormat::class)->money($row['claim']['claim_usd']).' USD' : '—' }}</td><td class="ltr-value">{{ $row['last_movement_at'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['last_movement_at'])->format('Y/m/d') : '—' }}</td></tr>@empty<tr><td colspan="6"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div>
    </section>

    <section class="cardx card-pad office-history">
        <div class="section-head"><div><div class="section-title">{{ __('inventory.recent_movements') }}</div><div class="card-sub">ورودی <span class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($incoming) }} kg</span> · خروجی <span class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($outgoing) }} kg</span> · طلب ذخیره <span class="ltr-value">{{ $canViewPrices ? app(\App\Support\NumberFormat::class)->money($storageClaim['claim_usd']).' USD' : '—' }}</span></div></div></div>
        <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('inventory.occurred_at') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('inventory.source') }}</th><th>{{ __('finance.description') }}</th><th>{{ __('inventory.incoming') }}</th><th>{{ __('inventory.outgoing') }}</th><th>ارزش</th><th>{{ __('inventory.balance') }}</th></tr></thead><tbody>@forelse($ledger as $entry)@php($movement = $entry['movement'])<tr><td class="ltr-value">@jalali($movement->occurred_at)</td><td>{{ $movement->product->name }}</td><td class="ltr-value">@include('inventory._source')</td><td>{{ $movement->movement_type->label() }}</td><td class="ltr-value">{{ $movement->movement_type->direction() === 1 ? app(\App\Support\NumberFormat::class)->weight($movement->quantity_kg) : '—' }}</td><td class="ltr-value">{{ $movement->movement_type->direction() === -1 ? app(\App\Support\NumberFormat::class)->weight($movement->quantity_kg) : '—' }}</td><td class="ltr-value">{{ !$canViewPrices || $movement->cost_amount_usd === null ? '—' : app(\App\Support\NumberFormat::class)->money($movement->cost_amount_usd).' USD' }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->weight($entry['running_balance']) }} kg</strong></td></tr>@empty<tr><td colspan="8"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div>
    </section>
@elseif($warehouses->isNotEmpty())
    <div class="empty">{{ __('common.no_records') }}</div>
@endif
@endsection
