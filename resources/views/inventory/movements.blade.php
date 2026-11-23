@extends('layouts.app')

@section('title', __('inventory.movements'))

@section('content')
    <x-page-header :title="__('inventory.movements')" :subtitle="__('inventory.movements_subtitle')" />
    <section class="cardx card-pad">
        <form class="filters" method="GET">
            <select class="control filter-control" name="warehouse_id"><option value="">{{ __('trades.warehouse') }}</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select>
            <select class="control filter-control" name="product_id"><option value="">{{ __('trades.product') }}</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) request('product_id') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select>
            <select class="control filter-control" name="movement_type"><option value="">{{ __('inventory.movement_type') }}</option>@foreach($movementTypes as $type)<option value="{{ $type->value }}" @selected(request('movement_type') === $type->value)>{{ $type->label() }}</option>@endforeach</select>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>
        @if($movements->isEmpty())
            <div class="empty"><i class="bi bi-arrow-left-right"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('inventory.occurred_at') }}</th><th>{{ __('inventory.movement_type') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('trades.warehouse') }}</th><th>{{ __('trades.quantity_kg') }}</th><th>{{ __('inventory.source') }}</th></tr></thead><tbody>
            @foreach($movements as $movement)<tr>
                <td class="ltr-value">@jalaliDateTime($movement->occurred_at)</td>
                <td><span class="badge {{ $movement->movement_type->direction() === 1 ? 'badge-green' : 'badge-red' }}">{{ $movement->movement_type->label() }}</span></td>
                <td>{{ $movement->product->name }}</td><td>{{ $movement->warehouse->name }}</td>
                <td class="ltr-value"><strong>{{ $movement->movement_type->direction() === 1 ? '+' : '−' }}{{ app(\App\Support\NumberFormat::class)->weight($movement->quantity_kg) }} kg</strong></td>
                <td>@include('inventory._source')</td>
            </tr>@endforeach
            </tbody></table></div>
            {{ $movements->links() }}
        @endif
    </section>
@endsection
