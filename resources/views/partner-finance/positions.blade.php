@extends('layouts.app')

@section('title', $kind === 'receivable' ? __('finance.receivables') : __('finance.payables'))

@section('content')
    <x-page-header :title="$kind === 'receivable' ? __('finance.receivables') : __('finance.payables')" :subtitle="$kind === 'receivable' ? __('finance.receivables_subtitle') : __('finance.payables_subtitle')">
        @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT'))<a class="btnx btn-primaryx" href="{{ route('office.accounts') }}"><i class="bi bi-plus-lg"></i>{{ __('finance.create_payment') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">@if($positions->isEmpty())<div class="empty"><i class="bi bi-check2-circle"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>@else<div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('finance.partner') }}</th><th>{{ __('partners.relationship') }}</th><th>{{ __('finance.amount') }}</th><th>{{ __('finance.last_activity') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
    @foreach($positions as $position)<tr><td class="td-main">{{ $position['partner']->name }} @unless($position['partner']->is_active)<span class="badge badge-gray">{{ __('common.inactive') }}</span>@endunless</td><td><div class="role-badges">@foreach($position['partner']->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach</div></td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->money($position['amount']) }} USD</strong></td><td class="ltr-value">@if($position['last_activity_date'])@jalali($position['last_activity_date'])@else{{ __('common.not_available') }}@endif</td><td><span class="badge {{ $kind === 'receivable' ? 'badge-amber' : 'badge-red' }}">{{ $kind === 'receivable' ? __('finance.partner_owes_company') : __('finance.company_owes_partner') }}</span></td><td><a href="{{ route('partners.statement', $position['partner']) }}">{{ __('finance.statement') }}</a></td></tr>@endforeach
    </tbody></table></div>@endif</section>
@endsection
