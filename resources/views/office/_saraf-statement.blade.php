<section class="cardx card-pad office-history" data-ledger>
    <div class="section-head">
        <div>
            <h2 class="section-title">گردش صراف</h2>
            <div class="card-sub">موقعیت دفتر با صراف · <strong dir="ltr">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($sarafBalance) }} USD</strong></div>
        </div>
    </div>
    @if($sarafStatement->isEmpty())
        <div class="empty compact">{{ __('common.no_records') }}</div>
    @else
        @php
            $numbers = app(\App\Support\NumberFormat::class);
        @endphp
        <x-ledger-search />
        <div class="table-wrap ledger-scroll" role="region" aria-label="گردش صراف" tabindex="0">
            <table class="tablex ledger-table">
                <thead>
                    <tr>
                        <th scope="col">تاریخ</th>
                        <th scope="col">تفصیل و مرجع</th>
                        <th scope="col">شخص مرتبط</th>
                        <th scope="col">معامله</th>
                        <th scope="col">دریافت <span class="ledger-unit">USD</span></th>
                        <th scope="col">پرداخت <span class="ledger-unit">USD</span></th>
                        <th scope="col">بیلانس جاری <span class="ledger-unit">USD</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sarafStatement as $entry)
                        <tr>
                            <td class="ledger-number">@jalali($entry['date'])</td>
                            <td>
                                {{ __('finance.ledger_types.'.$entry['type']) }}
                                <div><a class="table-link" href="{{ route('money-movements.show', $entry['source']) }}"><bdi>{{ $entry['reference'] }}</bdi></a></div>
                            </td>
                            <td>{{ $entry['related_party'] ?: '—' }}</td>
                            <td>@if($entry['deal'])<a class="table-link" href="{{ route('deals.show', $entry['source']->deal_id) }}"><bdi>{{ $entry['deal'] }}</bdi></a>@else—@endif</td>
                            <td class="ledger-number">{{ $entry['incoming'] !== null ? $numbers->money($entry['incoming']) : '—' }}</td>
                            <td class="ledger-number">{{ $entry['outgoing'] !== null ? $numbers->money($entry['outgoing']) : '—' }}</td>
                            <td class="ledger-number"><strong>{{ $numbers->money($entry['running_balance']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
