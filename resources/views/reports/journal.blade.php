@extends('reports.layout')
@section('title', 'روزنامچهٔ حساب و صراف')
@section('report')
@inject('numbers', 'App\Support\NumberFormat')
<x-page-header title="روزنامچهٔ حساب و صراف" />
@include('reports._workspace', ['reportUnit' => $currency, 'reportSubject' => $account?->name ?? $saraf?->name])
@include('reports._account-switch')
<section class="cardx card-pad">
    <form class="filters" method="get">
        <label class="field grow"><span>حساب مالی یا صراف</span>
            <select class="control" name="book">
                <optgroup label="حساب‌های مالی">@foreach($accounts as $item)
                    <option value="account:{{ $item->id }}" @selected($account?->is($item))>{{ $item->name }} · {{ $item->currency->value }}</option>
                @endforeach</optgroup>
                <optgroup label="صراف‌ها">@foreach($sarafs as $item)
                    <option value="saraf:{{ $item->id }}" @selected($saraf?->is($item))>{{ $item->name }} · {{ $item->reference_code }}</option>
                @endforeach</optgroup>
            </select>
        </label>
        <label class="field"><span>تاریخ</span><input class="control ltr-value" name="day" value="@jalali($date)"></label>
        <button class="btnx btn-primaryx">{{ __('common.view') }}</button>
    </form>
    @if($summary)
        <div class="report-totals">
            @foreach(['opening_balance' => 'day_opening', 'received' => 'received_total', 'paid' => 'paid_total', 'expenses' => 'expense_total', 'closing_balance' => 'day_closing'] as $key => $label)
                <span>{{ __('finance.'.$label) }} <strong><bdi>{{ $numbers->money($summary[$key]) }} {{ $currency }}</bdi></strong></span>
            @endforeach
        </div>
        <div class="table-wrap"><table class="tablex">
            <caption>حرکت‌های تأییدشده</caption>
            <thead><tr><th>سند</th><th>طرف معامله</th><th>توضیحات</th><th>مبلغ اصلی</th><th>افغانی در برابر یک دالر</th><th>دریافت ({{ $currency }})</th><th>پرداخت ({{ $currency }})</th><th>باقی ({{ $currency }})</th></tr></thead>
            <tbody>@forelse($summary['entries'] as $entry)
                @php($source = $entry['source'])
                <tr><td><bdi>{{ $source->reference_code }}</bdi></td><td>{{ $entry['party'] }}</td><td>{{ $entry['description'] }}</td>
                <td><bdi>{{ $numbers->money($source->amount ?? $source->amount_usd) }} {{ $source->currency?->value ?? 'USD' }}</bdi></td>
                <td><bdi>{{ $source->currency?->value === 'AFN' && $source->exchange_rate_to_usd !== null ? $numbers->decimal($source->exchange_rate_to_usd, 6) : '—' }}</bdi></td>
                <td><bdi>{{ $entry['direction'] === 1 ? $numbers->money($entry['amount']) : '—' }}</bdi></td>
                <td><bdi>{{ $entry['direction'] === -1 ? $numbers->money($entry['amount']) : '—' }}</bdi></td>
                <td><bdi>{{ $numbers->money($entry['running_balance']) }}</bdi></td></tr>
            @empty<tr><td colspan="8">{{ __('common.no_records') }}</td></tr>@endforelse</tbody>
        </table></div>
    @else<p>{{ __('common.no_records') }}</p>@endif
</section>
@endsection
