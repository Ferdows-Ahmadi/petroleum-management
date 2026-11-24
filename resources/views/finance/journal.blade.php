@extends('layouts.app')

@section('title', __('finance.journal'))

@section('content')
    <x-page-header :title="__('finance.journal')" :subtitle="__('finance.journal_subtitle')" />
    @if($canRecordFinance)
        <section class="cardx card-pad deal-entry-panel">
            <div class="section-head"><div><div class="section-title">ثبت رویداد روز</div><div class="card-sub">دریافت، پرداخت و مصرف از همین روزنامچه ثبت می‌شود.</div></div></div>
            <div class="money-action-grid">
                @php
                    $moneyInvalid = $errors->any() && old('return_to') === 'journal';
                    $journalMoneyDefaults = request()->filled('context') ? [
                        'money_route' => $context,
                        'financial_account_id' => $account?->id,
                        'saraf_id' => $saraf?->id,
                        'currency' => $account?->currency->value,
                    ] : [];
                    $expenseInvalid = $errors->any() && ! $moneyInvalid && array_key_exists('transaction_date', session()->getOldInput());
                    $expenseInput = $expenseInvalid ? session()->getOldInput() : [];
                @endphp
                <details id="money-entry" @if(request('entry') === 'money' || $moneyInvalid) open @endif>
                    <summary class="btnx btn-primaryx"><i class="bi bi-arrow-down-up"></i>دریافت / پرداخت</summary>
                    <form method="POST" action="{{ route('money-movements.store') }}">
                        @csrf
                        <input type="hidden" name="return_to" value="journal">
                        @include('money-movements._fields', ['moneyMovement' => null, 'counterparty' => null, 'restoreMovementInput' => $moneyInvalid])
                        <div class="form-actions"><button class="btnx btn-primaryx" type="submit" name="confirm_now" value="1">ثبت و تأیید</button></div>
                    </form>
                </details>
                <details id="expense-entry" @if(request('entry') === 'expense' || $expenseInvalid) open @endif>
                    <summary class="btnx btn-lightx"><i class="bi bi-receipt"></i>ثبت مصرف</summary>
                    <form method="POST" action="{{ route('office.finance.expenses.store') }}" data-money-form>
                        @csrf <x-operation-key :preserve="$expenseInvalid" />
                        <div class="form-grid-3">
                            <div class="field"><label for="expense_date">{{ __('finance.transaction_date') }}</label><input id="expense_date" class="control ltr-value" name="transaction_date" value="{{ $expenseInput['transaction_date'] ?? app(\App\Support\JalaliDate::class)->format(now()) }}" required>@if($expenseInvalid)<x-field-error name="transaction_date" />@endif</div>
                            <div class="field">
                                <label for="expense_account">{{ __('finance.financial_account') }}</label>
                                <select id="expense_account" class="control" name="financial_account_id" data-account-currency required>
                                    @foreach($accounts as $item)
                                        <option value="{{ $item->id }}" @selected((int) ($expenseInput['financial_account_id'] ?? 0) === $item->id) data-currency="{{ $item->currency->value }}">{{ $item->name }} · {{ $item->currency->value }}</option>
                                    @endforeach
                                </select>
                                @if($expenseInvalid)<x-field-error name="financial_account_id" />@endif
                            </div>
                            <div class="field"><label>{{ __('finance.currency') }}</label><input type="hidden" name="currency" value="USD" data-currency-input><div class="control ltr-value" data-currency-display>USD</div>@if($expenseInvalid)<x-field-error name="currency" />@endif</div>
                            <div class="field"><label for="expense_amount">{{ __('finance.amount') }}</label><input id="expense_amount" class="control ltr-value" name="amount" value="{{ $expenseInput['amount'] ?? '' }}" inputmode="decimal" required>@if($expenseInvalid)<x-field-error name="amount" />@endif</div>
                            <div class="field"><label for="expense_exchange_rate">{{ __('finance.exchange_rate') }} (AFN/USD)</label><input id="expense_exchange_rate" class="control ltr-value" name="exchange_rate_to_usd" value="{{ $expenseInput['exchange_rate_to_usd'] ?? '' }}" inputmode="decimal">@if($expenseInvalid)<x-field-error name="exchange_rate_to_usd" />@endif</div>
                            <div class="field">
                                <label for="expense_category">{{ __('finance.expense_category') }}</label>
                                <select id="expense_category" class="control" name="expense_category">
                                    @foreach($expenseCategories as $category)
                                        <option value="{{ $category->value }}" @selected(($expenseInput['expense_category'] ?? null) === $category->value)>{{ $category->label() }}</option>
                                    @endforeach
                                </select>
                                @if($expenseInvalid)<x-field-error name="expense_category" />@endif
                            </div>
                            <div class="field"><label for="expense_payee">{{ __('finance.payee') }}</label><input id="expense_payee" class="control" name="payee" value="{{ $expenseInput['payee'] ?? '' }}">@if($expenseInvalid)<x-field-error name="payee" />@endif</div>
                            <div class="field"><label for="expense_reference">{{ __('common.reference') }}</label><input id="expense_reference" class="control" name="reference" value="{{ $expenseInput['reference'] ?? '' }}">@if($expenseInvalid)<x-field-error name="reference" />@endif</div>
                            <div class="field"><label for="expense_description">{{ __('finance.description') }}</label><input id="expense_description" class="control" name="description" value="{{ $expenseInput['description'] ?? '' }}">@if($expenseInvalid)<x-field-error name="description" />@endif</div>
                        </div>
                        <div class="form-actions"><button class="btnx btn-primaryx" type="submit" name="confirm_now" value="1">ثبت و تأیید</button></div>
                    </form>
                </details>
            </div>
        </section>
    @endif
    <section class="cardx card-pad"><form class="filters" method="GET" data-journal-context-form>
        <input class="control filter-control ltr-value" aria-label="تاریخ روزنامچه" name="date" value="{{ app(\App\Support\JalaliDate::class)->format($date) }}" placeholder="1405/05/19">
        <select class="control" aria-label="نوع حساب" name="context" data-journal-context><option value="account" @selected($context === 'account')>{{ __('finance.financial_account') }}</option><option value="saraf" @selected($context === 'saraf')>{{ __('finance.saraf') }}</option></select>
        <select class="control grow" aria-label="حساب مالی" name="account_id" data-journal-account>
            @if($context === 'account' && ! $account)<option value="">{{ __('common.not_available') }}</option>@endif
            @foreach($accounts as $item)<option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->name }} · {{ $item->currency->value }}</option>@endforeach
        </select>
        <select class="control grow" aria-label="صراف" name="saraf_id" data-journal-saraf>
            @if($context === 'saraf' && ! $saraf)<option value="">{{ __('common.not_available') }}</option>@endif
            @foreach($sarafs as $item)<option value="{{ $item->id }}" @selected($saraf?->id === $item->id)>{{ $item->name }} · {{ $item->reference_code }}</option>@endforeach
        </select>
        <button class="btnx btn-lightx">{{ __('common.view') }}</button>
    </form></section>
    @if(!$summary)
        <div class="empty">{{ __('common.no_records') }}</div>
    @else
        <div class="kpis finance-kpis" style="margin-top:18px">@foreach([['finance.day_opening',$summary['opening_balance']],['finance.received_total',$summary['received']],['finance.paid_total',$summary['paid']],['finance.expense_total',$summary['expenses']],['finance.day_closing',$summary['closing_balance']]] as $item)<div class="cardx kpi"><div class="kpi-label">{{ __($item[0]) }}</div><div class="kpi-value ltr-value">{{ app(\App\Support\NumberFormat::class)->money($item[1]) }} {{ $currency }}</div></div>@endforeach</div>
        <section class="cardx card-pad" data-ledger>
            <div class="section-head">
                <div>
                    <h2 class="section-title">{{ $context === 'saraf' ? $saraf->name : $account->name }} · <bdi>@jalali($date)</bdi></h2>
                    <div class="card-sub">{{ $context === 'saraf' ? __('finance.saraf_position') : __('finance.account_formula') }}</div>
                </div>
            </div>
            @if($summary['entries']->isEmpty())
                <div class="empty compact">{{ __('common.no_records') }}</div>
            @else
                <x-ledger-search />
                <div class="table-wrap ledger-scroll" tabindex="0" role="region" aria-label="{{ __('finance.journal') }}">
                    <table class="tablex ledger-table">
                        <thead><tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">ساعت ثبت</th>
                            <th scope="col">{{ __('finance.partner') }}</th>
                            <th scope="col">{{ __('finance.incoming') }} <bdi class="ledger-unit">{{ $currency }}</bdi></th>
                            <th scope="col">{{ __('finance.outgoing') }} <bdi class="ledger-unit">{{ $currency }}</bdi></th>
                            <th scope="col">{{ __('finance.running_balance') }} <bdi class="ledger-unit">{{ $currency }}</bdi></th>
                            <th scope="col">باقی حساب طرف <bdi class="ledger-unit">USD</bdi></th>
                            <th scope="col">{{ __('finance.description') }}</th>
                            <th scope="col">معامله</th>
                            <th scope="col">{{ __('finance.saraf') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($summary['entries'] as $entry)
                                @php
                                    $source = $entry['source'];
                                    $isMovement = $source instanceof \App\Models\MoneyMovement;
                                    $link = match (true) {
                                        $isMovement => route('money-movements.show', $source),
                                        $source instanceof \App\Models\CapitalTransaction => route('capital-transactions.show', $source),
                                        $entry['kind'] === 'expense' => route('expenses.show', $source),
                                        default => route('payments.show', $source),
                                    };
                                    $numbers = app(\App\Support\NumberFormat::class);
                                @endphp
                                <tr>
                                    <td class="ledger-number"><a class="table-link" href="{{ $link }}">{{ $source->reference_code }}</a></td>
                                    <td class="ledger-number">{{ $entry['sort_at']->format('H:i') }}</td>
                                    <td>{{ $entry['party'] }}</td>
                                    <td class="ledger-number">{{ $entry['direction'] === 1 ? $numbers->money($entry['amount']) : '—' }}</td>
                                    <td class="ledger-number">{{ $entry['direction'] === -1 ? $numbers->money($entry['amount']) : '—' }}</td>
                                    <td class="ledger-number"><strong>{{ $numbers->money($entry['running_balance']) }} {{ $currency }}</strong></td>
                                    <td class="ledger-number">
                                        @if($entry['remaining_balance'] !== null)
                                            {{ $numbers->absoluteMoney($entry['remaining_balance']) }} USD
                                            <div class="td-sub" dir="rtl">{{ match (bccomp($entry['remaining_balance'], '0', 2)) {
                                                1 => __('finance.partner_owes_company'),
                                                -1 => __('finance.company_owes_partner'),
                                                default => __('finance.settled'),
                                            } }}</div>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="ledger-description">{{ $entry['description'] }}</td>
                                    <td class="ledger-description">
                                        @if($isMovement && $source->deal)
                                            <a class="table-link" href="{{ route('deals.show', $source->deal) }}">{{ \App\Support\RelatedDeals::label($source->deal) }}</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ $isMovement ? ($source->saraf?->name ?? '—') : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endif
@endsection
