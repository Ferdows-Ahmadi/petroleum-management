@extends('layouts.app')

@section('title', $mode === 'expenses' ? __('finance.expenses') : __('finance.payments'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT'))
    <x-page-header :title="$mode === 'expenses' ? __('finance.expenses') : __('finance.payments')" :subtitle="$mode === 'expenses' ? __('finance.expenses_subtitle') : __('finance.payments_subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route($mode.'.create') }}"><i class="bi bi-plus-lg"></i>{{ $mode === 'expenses' ? __('finance.create_expense') : __('finance.create_payment') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">
        <form class="filters" method="GET"><input class="control grow" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}..."><select class="control filter-control" name="status"><option value="">{{ __('common.all_statuses') }}</option>@foreach(\App\Enums\FinancialTransactionStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select><button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button></form>
        @if($transactions->isEmpty())<div class="empty"><i class="bi {{ $mode === 'expenses' ? 'bi-receipt' : 'bi-cash-stack' }}"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>@else
        <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('finance.transaction_date') }}</th><th>{{ __('finance.transaction_type') }}</th><th>{{ __('finance.partner') }}</th><th>{{ __('finance.financial_account') }}</th><th>{{ __('finance.native_amount') }}</th><th>{{ __('finance.amount_usd') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
        @foreach($transactions as $transaction)<tr><td>{{ $transaction->reference_code }}</td><td class="ltr-value">@jalali($transaction->transaction_date)</td><td><span class="badge {{ $transaction->transaction_type === \App\Enums\FinancialTransactionType::PaymentReceived ? 'badge-green' : 'badge-red' }}">{{ $transaction->transaction_type->label() }}</span></td><td>{{ $transaction->partner?->name ?: ($transaction->payee ?: __('common.not_available')) }}</td><td>{{ $transaction->account->name }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($transaction->amount) }} {{ $transaction->currency->value }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($transaction->amount_usd) }} USD</td><td><span class="badge {{ $transaction->status === \App\Enums\FinancialTransactionStatus::Confirmed ? 'badge-green' : 'badge-amber' }}">{{ $transaction->status->label() }}</span></td><td><div class="row-actions"><a href="{{ route($mode.'.show', $transaction) }}">{{ __('common.view') }}</a>@if($canManage && $transaction->status === \App\Enums\FinancialTransactionStatus::Draft)<a href="{{ route($mode.'.edit', $transaction) }}">{{ __('common.edit') }}</a>@endif</div></td></tr>@endforeach
        </tbody></table></div>{{ $transactions->links() }}@endif
    </section>
@endsection
