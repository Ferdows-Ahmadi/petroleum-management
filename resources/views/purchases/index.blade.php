@extends('layouts.app')

@section('title', __('trades.purchase.title'))

@section('content')
    @php($canViewPrices = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER'))
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="__('trades.purchase.title')" :subtitle="__('trades.purchase.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('purchases.create') }}"><i class="bi bi-plus-lg"></i>{{ __('trades.purchase.create') }}</a>@endif
    </x-page-header>

    <section class="cardx card-pad">
        <form class="filters" method="GET">
            <input class="control grow" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}...">
            <select class="control filter-control" name="status">
                <option value="">{{ __('common.all_statuses') }}</option>
                @foreach(\App\Enums\TransactionStatus::purchaseStatuses() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>

        @if($purchases->isEmpty())
            <div class="empty"><i class="bi bi-box-arrow-in-down"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr>
                <th>{{ __('common.reference') }}</th><th>{{ __('trades.purchase.date') }}</th><th>{{ __('trades.purchase.supplier') }}</th><th>{{ __('trades.quantity_kg') }}</th><th>{{ __('trades.total_amount') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th>
            </tr></thead><tbody>
            @foreach($purchases as $purchase)
                <tr>
                    <td><div class="td-main">{{ $purchase->reference_code }}</div><div class="td-sub">{{ $purchase->reference_number ?: __('common.not_available') }}</div></td>
                    <td class="ltr-value">@jalali($purchase->purchase_date)</td>
                    <td>{{ $purchase->supplier->name }}</td>
                    <td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($purchase->total_quantity_kg) }} kg</td>
                    <td class="ltr-value">{{ $canViewPrices ? (app(\App\Support\NumberFormat::class)->money($purchase->total_amount)) : '—' }} {{ $purchase->currency->value }}</td>
                    <td><x-transaction-status-badge :status="$purchase->status" /></td>
                    <td><div class="row-actions"><a href="{{ route('purchases.show', $purchase) }}">{{ __('common.view') }}</a>@if($canManage && $purchase->status === \App\Enums\TransactionStatus::Draft)<a href="{{ route('purchases.edit', $purchase) }}">{{ __('common.edit') }}</a>@endif</div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            {{ $purchases->links() }}
        @endif
    </section>
@endsection
