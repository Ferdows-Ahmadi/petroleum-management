@extends('layouts.app')

@section('title', __('inventory.balances'))

@section('content')
    <x-page-header :title="__('inventory.balances')" :subtitle="__('inventory.balances_subtitle')" />
    <div class="notice subtle-notice"><i class="bi bi-calculator"></i><div><strong>{{ __('inventory.balance') }}</strong>{{ __('inventory.calculated_notice') }}</div></div>
    <section class="cardx card-pad">
        <form class="filters" method="GET">
            <select class="control filter-control" name="warehouse_id"><option value="">{{ __('trades.warehouse') }} — {{ __('common.all_statuses') }}</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
            <select class="control filter-control" name="product_id"><option value="">{{ __('trades.product') }} — {{ __('common.all_statuses') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>
        <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('trades.warehouse') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('inventory.balance') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td><div class="td-main">{{ $row->warehouse->name }}</div><div class="td-sub">{{ $row->warehouse->reference_code }}</div></td><td>{{ $row->product->name }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->weight($row->balance) }} kg</strong></td><td><a href="{{ route('warehouses.show', $row->warehouse) }}">{{ __('common.view') }}</a></td></tr>@empty<tr><td colspan="4"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
