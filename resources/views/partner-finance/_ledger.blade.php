@if($statement->isEmpty())
    <div class="empty compact">{{ __('common.no_records') }}</div>
@else
    @php
        $numbers = app(\App\Support\NumberFormat::class);
    @endphp
    <x-ledger-search />
    <div class="table-wrap ledger-scroll" role="region" aria-label="{{ __('finance.statement') }}" tabindex="0">
        <table class="tablex ledger-table">
            <thead>
                <tr>
                    <th scope="col">{{ __('finance.transaction_date') }}</th>
                    <th scope="col">{{ __('finance.transaction_type') }}</th>
                    <th scope="col">{{ __('common.reference') }}</th>
                    <th scope="col">بدهکار <span class="ledger-unit">USD</span></th>
                    <th scope="col">بستانکار <span class="ledger-unit">USD</span></th>
                    <th scope="col">بیلانس جاری <span class="ledger-unit">USD</span></th>
                    @if($showTradeDetails)
                        <th scope="col">مقدار <span class="ledger-unit">kg</span></th>
                        <th scope="col">نرخ فی تن <span class="ledger-unit">USD</span></th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($statement as $entry)
                    @php
                        $source = $entry['source'];
                        $detailRoute = match ($entry['type']) {
                            'SALE' => 'sales.show',
                            'PURCHASE' => 'purchases.show',
                            'DEAL_SOURCE', 'DEAL_DESTINATION' => 'deals.show',
                            'CUSTOMER_RECEIPT', 'SUPPLIER_PAYMENT' => $canViewCoreFinance ? 'money-movements.show' : null,
                            'PAYMENT_RECEIVED', 'PAYMENT_PAID' => $canViewCoreFinance ? 'payments.show' : null,
                            default => null,
                        };
                    @endphp
                    <tr>
                        <td class="ledger-number">@jalali($entry['date'])</td>
                        <td>
                            {{ __('finance.ledger_types.'.$entry['type']) }}
                            @if($showTradeDetails && !empty($entry['related_party']))<div class="td-sub">{{ __('finance.saraf') }} · {{ $entry['related_party'] }}</div>@endif
                        </td>
                        <td>
                            @if($detailRoute)
                                <a class="table-link" href="{{ route($detailRoute, $source) }}"><bdi>{{ $entry['reference'] }}</bdi></a>
                            @else
                                <bdi>{{ $entry['reference'] }}</bdi>
                            @endif
                            @if($canViewCoreFinance && !empty($entry['deal']))
                                <div class="td-sub"><a class="table-link" href="{{ route('deals.show', $source->deal_id) }}"><bdi>{{ $entry['deal'] }}</bdi></a></div>
                            @endif
                        </td>
                        <td class="ledger-number">{{ $entry['debit'] !== null ? $numbers->money($entry['debit']) : '—' }}</td>
                        <td class="ledger-number">{{ $entry['credit'] !== null ? $numbers->money($entry['credit']) : '—' }}</td>
                        <td class="ledger-number">
                            @if($showTradeDetails)
                                <strong>{{ $numbers->money($entry['running_balance']) }}</strong>
                            @else
                                <strong>{{ $numbers->absoluteMoney($entry['running_balance']) }}</strong>
                                <div class="td-sub" dir="rtl">{{ match (bccomp($entry['running_balance'], '0', 2)) {
                                    1 => __('finance.partner_owes_company'), -1 => __('finance.company_owes_partner'), default => __('finance.settled')
                                } }}</div>
                            @endif
                        </td>
                        @if($showTradeDetails)
                            @php
                                $quantity = match (true) {
                                    $source instanceof \App\Models\Sale => $source->total_final_quantity_kg,
                                    $source instanceof \App\Models\Purchase => $source->total_quantity_kg,
                                    default => $entry['quantity_kg'] ?? null,
                                };
                                $rate = ($source instanceof \App\Models\Sale || $source instanceof \App\Models\Purchase) && $source->items->count() === 1
                                    ? $source->items->first()->rate_per_ton : ($entry['rate_per_ton'] ?? null);
                            @endphp
                            <td class="ledger-number">{{ $quantity !== null ? $numbers->weight($quantity) : '—' }}</td>
                            <td class="ledger-number">{{ $rate !== null ? $numbers->rate($rate) : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
