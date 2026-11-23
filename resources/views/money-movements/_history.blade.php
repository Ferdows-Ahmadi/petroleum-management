<section class="cardx card-pad payment-history">
    <h2 class="section-title">تاریخچه دریافت و پرداخت</h2>
    @forelse($history as $item)
        @php
            $legacy = $item instanceof \App\Models\FinancialTransaction;
            $person = $profile ? $selectedPartner : $item->counterparty;
            $linkedDeal = $profile && ! $legacy ? $item->deal : null;
        @endphp
        <article class="payment-event">
            <div>
                <strong>{{ $legacy ? $item->transaction_type->label() : $item->movement_type->label() }}</strong>
                <bdi>@jalali($legacy ? $item->transaction_date : $item->movement_date)</bdi>
                <div>{{ $person?->name }} <bdi>{{ $person?->reference_code }}</bdi></div>
                @if($profile)
                    @if($linkedDeal)<a href="{{ route('deals.show', $linkedDeal) }}">{{ $linkedDeal->reference_code }}</a>
                    @else<span class="card-sub">{{ __('finance.unallocated') }}</span>@endif
                @endif
            </div>
            <div>
                <strong dir="ltr">{{ app(\App\Support\NumberFormat::class)->money($item->amount) }} {{ $item->currency->value }}</strong>
                <span class="badge {{ $item->status->value === 'CONFIRMED' ? 'badge-green' : 'badge-gray' }}">{{ $item->status->label() }}</span>
                <div>{{ $item->financial_account_id ? __('finance.financial_account').' · '.$item->account?->name : __('finance.saraf').' · '.$item->saraf?->name }}</div>
                <a href="{{ route($legacy ? 'payments.show' : 'money-movements.show', $item) }}"><bdi>{{ $item->reference_code }}</bdi></a>
                @if(filled($item->reference)) · <bdi>{{ $item->reference }}</bdi>@endif
            </div>
        </article>
    @empty
        <p class="card-sub">تا اکنون دریافت یا پرداختی ثبت نشده است.</p>
    @endforelse
</section>
