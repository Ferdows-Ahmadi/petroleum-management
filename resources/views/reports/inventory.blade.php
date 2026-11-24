@extends('reports.layout')
@section('title', 'گزارش موجودی ذخایر')
@section('report')
@inject('numbers', 'App\Support\NumberFormat')
<x-page-header title="گزارش موجودی ذخایر" />
@include('reports._workspace', ['reportUnit' => $showValue ? 'kg / USD' : 'kg'])
<section class="cardx card-pad">
    <form method="get" class="filters">
        <label class="field"><span>{{ __('reports.date_to') }}</span><input class="control ltr-value" name="date_to" value="{{ $dateInputs['date_to'] ?? '' }}" placeholder="۱۴۰۵/۰۱/۰۱"></label>
        <label class="field grow"><span>{{ __('trades.product') }}</span>
            <select class="control" name="product_id"><option value="">{{ __('reports.all_products') }}</option>
                @foreach($products as $product)<option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>{{ $product->name }}</option>@endforeach
            </select>
        </label>
        <label class="field grow"><span>{{ __('trades.warehouse') }}</span>
            <select class="control" name="warehouse_id"><option value="">{{ __('reports.all_warehouses') }}</option>
                @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>@endforeach
            </select>
        </label>
        <button class="btnx btn-primaryx">{{ __('common.filter') }}</button>
    </form>
    <div class="report-totals">
        <span>{{ __('reports.total_quantity') }} <strong><bdi>{{ $numbers->weight($quantity) }} kg</bdi></strong></span>
        @if($showValue)<span>{{ __('reports.inventory_value') }} <strong><bdi>{{ $value === null ? 'نامشخص' : $numbers->money($value).' USD' }}</bdi></strong></span>@endif
    </div>
    <div class="table-wrap"><table class="tablex">
        <thead><tr><th>{{ __('trades.warehouse') }}</th><th>{{ __('trades.product') }}</th><th>موجودی (kg)</th>@if($showValue)<th>ارزش موجودی (USD)</th>@endif</tr></thead>
        <tbody>@forelse($streams as $stream)
            <tr><td>{{ $stream['warehouse']->name }}</td><td>{{ $stream['product']->name }}</td><td><bdi>{{ $numbers->weight($stream['quantity_kg']) }}</bdi></td>
            @if($showValue)<td><bdi>{{ $stream['value_usd'] === null ? 'نامشخص' : $numbers->money($stream['value_usd']) }}</bdi></td>@endif</tr>
        @empty<tr><td colspan="{{ $showValue ? 4 : 3 }}">{{ __('common.no_records') }}</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
