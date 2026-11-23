@extends('layouts.app')

@section('title', __('trades.sale.title'))

@section('content')
    @php($canViewPrices = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER'))
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="__('trades.sale.title')" :subtitle="__('trades.sale.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('sales.create') }}"><i class="bi bi-plus-lg"></i>{{ __('trades.sale.create') }}</a>@endif
    </x-page-header>

    <section class="cardx card-pad">
        <form class="filters" method="GET">
            <input class="control grow" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}...">
            <select class="control filter-control" name="status">
                <option value="">{{ __('common.all_statuses') }}</option>
                @foreach(\App\Enums\TransactionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>

        @if($sales->isEmpty())
            <div class="empty"><i class="bi bi-box-arrow-up"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr>
                <th>{{ __('common.reference') }}</th><th>{{ __('trades.sale.date') }}</th><th>{{ __('trades.sale.customer') }}</th><th>{{ __('trades.final_quantity_kg') }}</th><th>{{ __('trades.total_amount') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th>
            </tr></thead><tbody>
            @foreach($sales as $sale)
                <tr>
                    <td><div class="td-main">{{ $sale->reference_code }}</div><div class="td-sub">{{ $sale->reference_number ?: __('common.not_available') }}</div></td>
                    <td class="ltr-value">@jalali($sale->sale_date)</td>
                    <td>{{ $sale->customer->name }}</td>
                    <td class="ltr-value">{{ $sale->items->every(fn ($item) => $item->final_quantity_kg !== null) ? app(\App\Support\NumberFormat::class)->weight($sale->total_final_quantity_kg).' kg' : __('trades.pending_weighing') }}</td>
                    <td class="ltr-value">{{ $canViewPrices ? ($sale->items->every(fn ($item) => $item->final_quantity_kg !== null) ? app(\App\Support\NumberFormat::class)->money($sale->total_amount).' '.$sale->currency->value : __('trades.pending_weighing')) : '—' }}</td>
                    <td><x-transaction-status-badge :status="$sale->status" /></td>
                    <td><div class="row-actions"><a href="{{ route('sales.show', $sale) }}">{{ __('common.view') }}</a>@if($canManage && $sale->status === \App\Enums\TransactionStatus::Draft)<a href="{{ route('sales.edit', $sale) }}">{{ __('common.edit') }}</a>@endif</div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            {{ $sales->links() }}
        @endif
    </section>
@endsection
