@extends('reports.layout')
@section('title', __('finance.statement'))
@section('report')
@inject('numbers', 'App\Support\NumberFormat')
<x-page-header :title="__('finance.statement')" />
@include('reports._workspace', ['reportSubject' => $selected->name.' · '.$selected->reference_code])
@include('reports._account-switch')
<section class="cardx card-pad">
    @include('reports._partner-filter')
    <div class="report-totals">
        <span>باقی اول دوره <strong><bdi>{{ $numbers->money($opening) }} USD</bdi></strong></span>
        <span>باقی آخر دوره <strong><bdi>{{ $numbers->money($closing) }} USD</bdi></strong></span>
    </div>
    <div class="table-wrap"><table class="tablex">
        <thead><tr><th>تاریخ</th><th>سند</th><th>{{ __('finance.debit') }} (USD)</th><th>{{ __('finance.credit') }} (USD)</th><th>باقی (USD)</th></tr></thead>
        <tbody>@forelse($rows as $row)
            <tr><td><bdi>@jalali($row['date'])</bdi></td><td><bdi>{{ $row['reference'] }}</bdi></td>
            <td><bdi>{{ $row['debit'] !== null ? $numbers->money($row['debit']) : '—' }}</bdi></td>
            <td><bdi>{{ $row['credit'] !== null ? $numbers->money($row['credit']) : '—' }}</bdi></td>
            <td><bdi>{{ $numbers->money($row['running_balance']) }}</bdi></td></tr>
        @empty<tr><td colspan="5">{{ __('common.no_records') }}</td></tr>@endforelse</tbody>
    </table></div>
</section>
@endsection
