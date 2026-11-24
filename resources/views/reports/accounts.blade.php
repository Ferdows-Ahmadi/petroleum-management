@extends('reports.layout')
@section('title', 'گزارش حساب اشخاص')
@section('report')
@inject('numbers', 'App\Support\NumberFormat')
<x-page-header title="گزارش حساب اشخاص" />
@include('reports._workspace', ['reportUnit' => $showBalances ? 'USD' : false])
@include('reports._account-switch')
<section class="cardx card-pad">
    @include('reports._partner-filter')
    <div class="report-totals">
        <span>تعداد اشخاص <bdi>{{ $rows->count() }}</bdi></span>
        @if($showBalances)
            <span>مجموع دریافتنی <strong><bdi>{{ $numbers->money($receivables) }} USD</bdi></strong></span>
            <span>مجموع پرداختنی <strong><bdi>{{ $numbers->money($payables) }} USD</bdi></strong></span>
        @endif
    </div>
    <div class="table-wrap"><table class="tablex">
        <thead><tr><th>شخص</th><th>نقش</th>@if($showBalances)<th>دریافتنی (USD)</th><th>پرداختنی (USD)</th><th>{{ __('finance.last_activity') }}</th>@endif</tr></thead>
        <tbody>
        @forelse($rows as $partner)
            <tr><td>
                @if($showBalances)<a href="{{ route('reports.accounts', ['partner_id' => $partner->id]) }}">{{ $partner->name }} · {{ $partner->reference_code }}</a>
                @else{{ $partner->name }} · {{ $partner->reference_code }}@endif
            </td><td>{{ $partner->roles->map(fn ($role) => $role->role->label())->join('، ') }}</td>
            @if($showBalances)
                @php($balance = $positions[$partner->id]['balance'] ?? '0.00')
                <td><bdi>{{ bccomp($balance, '0', 2) > 0 ? $numbers->money($balance) : '—' }}</bdi></td>
                <td><bdi>{{ bccomp($balance, '0', 2) < 0 ? $numbers->absoluteMoney($balance) : '—' }}</bdi></td>
                <td><bdi>{{ !empty($positions[$partner->id]['last_activity']) ? app(\App\Support\JalaliDate::class)->format($positions[$partner->id]['last_activity']) : '—' }}</bdi></td>
            @endif
            </tr>
        @empty<tr><td colspan="{{ $showBalances ? 5 : 2 }}">{{ __('common.no_records') }}</td></tr>@endforelse
        </tbody>
    </table></div>
</section>
@endsection
