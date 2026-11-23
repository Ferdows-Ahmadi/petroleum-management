@extends('layouts.app')

@section('title', __('finance.journal'))

@section('content')
@if($errors->any())<div class="alertx alert-errorx"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <x-page-header :title="__('finance.journal')" :subtitle="__('finance.journal_subtitle')" />
    @if($canRecordFinance)
        <section class="cardx card-pad deal-entry-panel">
            <div class="section-head"><div><div class="section-title">ثبت رویداد روز</div><div class="card-sub">دریافت، پرداخت و مصرف از همین روزنامچه ثبت می‌شود.</div></div></div>
            <div class="money-action-grid">
                <details @if($errors->hasAny(['movement_type','counterparty_id','amount','saraf_id','financial_account_id'])) open @endif><summary class="btnx btn-primaryx"><i class="bi bi-arrow-down-up"></i>دریافت / پرداخت</summary><form method="POST" action="{{ route('money-movements.store') }}">@csrf <x-operation-key /><input type="hidden" name="confirm_now" value="1"><input type="hidden" name="return_to" value="journal">@include('money-movements._fields', ['moneyMovement' => null, 'counterparty' => null])<div class="form-actions"><button class="btnx btn-primaryx">ثبت و تأیید</button></div></form></details>
                <details @if($errors->any()) open @endif><summary class="btnx btn-lightx"><i class="bi bi-receipt"></i>ثبت مصرف</summary><form method="POST" action="{{ route('office.finance.expenses.store') }}" data-money-form>@csrf <x-operation-key /><div class="form-grid-3"><div class="field"><label>تاریخ</label><input class="control ltr-value" name="transaction_date" value="{{ old('transaction_date', app(\App\Support\JalaliDate::class)->format(now())) }}" required></div><div class="field"><label>حساب مالی</label><select class="control" name="financial_account_id" data-account-currency required>@foreach($accounts as $item)<option value="{{ $item->id }}" @selected((int)old('financial_account_id') === $item->id) data-currency="{{ $item->currency->value }}">{{ $item->name }} · {{ $item->currency->value }}</option>@endforeach</select></div><div class="field"><label>واحد پول</label><input type="hidden" name="currency" value="USD" data-currency-input><div class="control ltr-value" data-currency-display>USD</div></div><div class="field"><label>مبلغ</label><input class="control ltr-value" name="amount" value="{{ old('amount') }}" inputmode="decimal" required></div><div class="field"><label>نرخ تبدیل (AFN/USD)</label><input class="control ltr-value" name="exchange_rate_to_usd" value="{{ old('exchange_rate_to_usd') }}" inputmode="decimal"></div><div class="field"><label>دسته مصرف</label><select class="control" name="expense_category">@foreach($expenseCategories as $category)<option value="{{ $category->value }}" @selected(old('expense_category') === $category->value)>{{ $category->label() }}</option>@endforeach</select></div><div class="field"><label>دریافت‌کننده</label><input class="control" name="payee" value="{{ old('payee') }}"></div><div class="field"><label>مرجع</label><input class="control" name="reference" value="{{ old('reference') }}"></div><div class="field"><label>تفصیل</label><input class="control" name="description" value="{{ old('description') }}"></div></div><div class="form-actions"><button class="btnx btn-primaryx">ثبت و تأیید</button></div></form></details>
            </div>
        </section>
    @endif
    <section class="cardx card-pad"><form class="filters" method="GET" data-journal-context-form><input class="control filter-control ltr-value" name="date" value="{{ app(\App\Support\JalaliDate::class)->format($date) }}" placeholder="1405/05/19"><select class="control" name="context" data-journal-context><option value="account" @selected($context === 'account')>{{ __('finance.financial_account') }}</option><option value="saraf" @selected($context === 'saraf')>{{ __('finance.saraf') }}</option></select><select class="control grow" name="account_id" data-journal-account>@foreach($accounts as $item)<option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->name }} · {{ $item->currency->value }}</option>@endforeach</select><select class="control grow" name="saraf_id" data-journal-saraf>@foreach($sarafs as $item)<option value="{{ $item->id }}" @selected($saraf?->id === $item->id)>{{ $item->name }}</option>@endforeach</select><button class="btnx btn-lightx">{{ __('common.view') }}</button></form></section>
    @if(!$summary)
        <div class="empty">{{ __('common.no_records') }}</div>
    @else
        <div class="kpis finance-kpis" style="margin-top:18px">@foreach([['finance.day_opening',$summary['opening_balance']],['finance.received_total',$summary['received']],['finance.paid_total',$summary['paid']],['finance.expense_total',$summary['expenses']],['finance.day_closing',$summary['closing_balance']]] as $item)<div class="cardx kpi"><div class="kpi-label">{{ __($item[0]) }}</div><div class="kpi-value ltr-value">{{ app(\App\Support\NumberFormat::class)->money($item[1]) }} {{ $currency }}</div></div>@endforeach</div>
        <section class="cardx card-pad">
            <div class="section-head"><div><div class="section-title">{{ $context === 'saraf' ? $saraf->name : $account->name }}</div><div class="card-sub">{{ $context === 'saraf' ? __('finance.saraf_position') : __('finance.account_formula') }}</div></div></div>
            @if($summary['entries']->isEmpty())
                <div class="empty compact">{{ __('common.no_records') }}</div>
            @else
                <div class="table-wrap"><table class="tablex">
                    <thead><tr><th>{{ __('finance.transaction_date') }}</th><th>{{ __('finance.description') }}</th><th>{{ __('finance.partner') }}</th><th>{{ __('finance.saraf') }}</th><th>معامله</th><th>مرجع</th><th>{{ __('finance.incoming') }}</th><th>{{ __('finance.outgoing') }}</th><th>باقی حساب</th><th>{{ __('finance.running_balance') }}</th></tr></thead>
                    <tbody>@foreach($summary['entries'] as $entry)@php($source = $entry['source'] ?? null)<tr>
                        <td class="ltr-value">{{ $entry['sort_at']->format('H:i') }}</td>
                        <td>@if($source instanceof \App\Models\MoneyMovement)<a href="{{ route('money-movements.show', $source) }}">{{ $entry['description'] }}</a>@else{{ $entry['description'] }}@endif</td>
                        <td>{{ $entry['party'] }}</td>
                        <td>{{ $source instanceof \App\Models\MoneyMovement ? ($source->saraf?->name ?? '—') : '—' }}</td>
                        <td>@if($source instanceof \App\Models\MoneyMovement && $source->deal)<a class="table-link ltr-value" href="{{ route('deals.show', $source->deal) }}">{{ $source->deal->reference_code }}</a>@else—@endif</td>
                        <td class="ltr-value">{{ $source?->reference_code ?? '—' }}</td>
                        <td class="ltr-value">{{ $entry['direction'] === 1 ? app(\App\Support\NumberFormat::class)->money($entry['amount']) : '—' }}</td>
                        <td class="ltr-value">{{ $entry['direction'] === -1 ? app(\App\Support\NumberFormat::class)->money($entry['amount']) : '—' }}</td>
                        <td class="ltr-value">{{ $entry['remaining_balance'] !== null ? app(\App\Support\NumberFormat::class)->absoluteMoney($entry['remaining_balance']).' USD' : '—' }}</td>
                        <td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->money($entry['running_balance']) }} {{ $currency }}</strong></td>
                    </tr>@endforeach</tbody>
                </table></div>
            @endif
        </section>
    @endif
@endsection
