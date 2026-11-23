@php
    $receipt = \App\Enums\MoneyMovementType::CustomerReceipt->value;
    $payment = \App\Enums\MoneyMovementType::SupplierPayment->value;
    $sides = [
        [$receipt, 'دریافت از مشتری', 'sale_receivable', 'received_from_customer', 'remaining_receivable', $allowReceipt],
        [$payment, 'پرداخت به عرضه‌کننده', 'purchase_obligation', 'paid_to_supplier', 'remaining_payable', $allowPayment],
    ];
@endphp
<section class="cardx card-pad payment-overview">
    <h2 class="section-title">{{ $profile ? 'دریافت و پرداخت این حساب' : 'دریافت و پرداخت معامله' }}</h2>
    <p class="card-sub">{{ $profile ? $selectedPartner->name.' · '.$selectedPartner->reference_code.' · مجموع همه معاملات و مانده افتتاحیه' : $deal->reference_code }}</p>
    <div class="payment-sides">
        @foreach($sides as [$type, $title, $original, $confirmed, $remaining, $allowed])
            <section class="payment-side" data-payment-side="{{ $type }}">
                <h3>{{ $title }}</h3>
                <dl>
                    <div><dt>مبلغ اصلی</dt><dd dir="ltr">{{ app(\App\Support\NumberFormat::class)->money($totals[$original]) }} USD</dd></div>
                    <div><dt>{{ $type === $receipt ? 'دریافت تأییدشده' : 'پرداخت تأییدشده' }}</dt><dd dir="ltr">{{ app(\App\Support\NumberFormat::class)->money($totals[$confirmed]) }} USD</dd></div>
                    <div><dt>{{ bccomp($totals[$remaining], '0', 2) < 0 ? ($type === $receipt ? 'اعتبار مشتری' : 'پیش‌پرداخت به عرضه‌کننده') : 'مانده' }}</dt><dd dir="ltr">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($totals[$remaining]) }} USD</dd></div>
                </dl>
                @if($allowed)
                    <a class="btnx btn-primaryx" data-payment-action href="{{ request()->fullUrlWithQuery(['entry' => 'money', 'direction' => $type]) }}#money-entry">{{ $type === $receipt ? 'ثبت دریافت از مشتری' : 'ثبت پرداخت به عرضه‌کننده' }}</a>
                @endif
            </section>
        @endforeach
    </div>
    @unless($profile)<div class="card-sub">{{ __('settlements.financial_section') }} · <span class="badge badge-gray">{{ __('finance.settlement_states.'.$finance['settlement_state']) }}</span></div>@endunless
</section>
