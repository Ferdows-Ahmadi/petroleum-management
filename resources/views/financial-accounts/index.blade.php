@extends('layouts.app')

@section('title', __('finance.accounts'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT'))
    <x-page-header :title="__('finance.accounts')" :subtitle="__('finance.accounts_subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('financial-accounts.create') }}"><i class="bi bi-plus-lg"></i>{{ __('finance.account_create') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">
        @if($accounts->isEmpty())<div class="empty"><i class="bi bi-wallet2"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>@else
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('finance.account_name') }}</th><th>{{ __('finance.account_type') }}</th><th>{{ __('finance.currency') }}</th><th>{{ __('finance.current_balance') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
            @foreach($accounts as $account)<tr><td>{{ $account->reference_code }}</td><td class="td-main">{{ $account->name }}</td><td>{{ $account->account_type->label() }}</td><td class="ltr-value">{{ $account->currency->value }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->money($accountBalances[$account->id]) }} {{ $account->currency->value }}</strong></td><td><x-status-badge :active="$account->is_active" /></td><td><div class="row-actions"><a href="{{ route('financial-accounts.show', $account) }}">{{ __('common.view') }}</a>@if($canManage)<a href="{{ route('financial-accounts.edit', $account) }}">{{ __('common.edit') }}</a>@endif</div></td></tr>@endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
