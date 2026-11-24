@extends('layouts.app')
@section('title', __('dashboard.title'))
@section('content')
<x-page-header :title="__('dashboard.title')" :subtitle="__('dashboard.subtitle')">
    @if(auth()->user()->hasAnyRole(\App\Enums\UserRole::GeneralManager, \App\Enums\UserRole::SystemAdmin, \App\Enums\UserRole::PurchaseSalesOfficer))
    <a class="btnx btn-primaryx" href="{{ route('deals.index') }}#new-deal"><i class="bi bi-plus-lg"></i>معامله جدید</a>
    @endif
</x-page-header>

<section class="summary-strip" aria-label="خلاصه امروز">
    <div class="summary-cell"><div class="summary-label">{{ __('dashboard.today_purchases') }}</div><div class="summary-value ltr-value">{{ $canViewMoney ? app(\App\Support\NumberFormat::class)->money($metrics['today_purchases']).' USD' : '—' }}</div></div>
    <div class="summary-cell"><div class="summary-label">{{ __('dashboard.today_sales') }}</div><div class="summary-value ltr-value">{{ $canViewMoney ? app(\App\Support\NumberFormat::class)->money($metrics['today_sales']).' USD' : '—' }}</div></div>
    <div class="summary-cell"><div class="summary-label">نتیجه تصفیه امروز</div><div class="summary-value accent ltr-value">{{ $canViewMoney ? app(\App\Support\NumberFormat::class)->money($todaySettlementResult).' USD' : '—' }}</div></div>
    <div class="summary-cell"><div class="summary-label">معاملات باز</div><div class="summary-value ltr-value">{{ $openDeals }}</div></div>
</section>

<section class="cardx card-pad">
    <div class="section-head"><div><div class="section-title">آخرین معاملات</div><div class="card-sub">آخرین فعالیت تجارتی ثبت‌شده</div></div><a class="section-link" href="{{ route('office.transactions') }}">مشاهدهٔ همه</a></div>
    <div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>مرجع</th><th>عرضه‌کننده</th><th>مشتری</th><th>محصول</th><th>مقدار</th>@if($canViewMoney)<th>نتیجه</th>@endif<th>وضعیت</th></tr></thead><tbody>
        @forelse($recentDeals as $deal)
            @php
                $quantity = $deal->total_delivered_quantity;
                $sourceNames = $deal->sources->map(fn ($line) => $line->supplier?->name ?: $line->warehouse?->name)->filter()->unique()->implode('، ');
                $destinationNames = $deal->destinations->map(fn ($line) => $line->customer?->name ?: $line->warehouse?->name)->filter()->unique()->implode('، ');
            @endphp
            <tr><td class="ltr-value">@jalali($deal->deal_date)</td><td><a class="table-link ltr-value" href="{{ route('deals.show', $deal) }}">{{ $deal->reference_code }}</a></td><td>{{ $sourceNames ?: '—' }}</td><td>{{ $destinationNames ?: '—' }}</td><td>{{ $deal->product?->name ?? '—' }}</td><td class="ltr-value">{{ $quantity !== null ? app(\App\Support\NumberFormat::class)->weight($quantity).' kg' : '—' }}</td>@if($canViewMoney)<td class="ltr-value">{{ $deal->latestConfirmedSettlement ? app(\App\Support\NumberFormat::class)->money($deal->latestConfirmedSettlement->final_result_usd).' USD' : '—' }}</td>@endif<td><span class="badge {{ $deal->status->value === 'COMPLETED' ? 'badge-green' : 'badge-amber' }}">{{ $deal->status->label() }}</span></td></tr>
        @empty<tr><td colspan="{{ $canViewMoney ? 8 : 7 }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse
    </tbody></table></div>
</section>

@unless($canViewMoney)
<section class="cardx card-pad office-history">
    <div class="section-head"><div><div class="section-title">فعالیت اخیر</div><div class="card-sub">فعالیت عملیاتی مرتبط با نقش شما</div></div></div>
    <div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>نوع</th><th>شخص</th><th>وضعیت</th></tr></thead><tbody>@forelse($recentActivity as $activity)<tr><td class="ltr-value">@jalali($activity['date'])</td><td>{{ $activity['type'] }}</td><td>{{ $activity['party'] }}</td><td>{{ $activity['status'] }}</td></tr>@empty<tr><td colspan="4"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div>
</section>
@endunless

@if($canViewMoney)
<section class="cardx card-pad office-history">
    <div class="section-head"><div><div class="section-title">حساب‌های مهم</div><div class="card-sub">بیشترین مبالغ دریافتنی و پرداختنی</div></div><a class="section-link" href="{{ route('office.accounts') }}">مشاهدهٔ حساب‌ها</a></div>
    <div class="table-wrap"><table class="tablex"><thead><tr><th>شخص</th><th>نوع حساب</th><th>مبلغ</th><th>آخرین فعالیت</th></tr></thead><tbody>
        @forelse($importantAccounts as $row)<tr><td><a class="table-link" href="{{ route('office.accounts', ['partner_id' => $row['partner']->id]) }}">{{ $row['partner']->name }}</a></td><td>{{ bccomp($row['balance'], '0', 2) > 0 ? 'دریافتنی' : 'پرداختنی' }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($row['amount']) }} USD</td><td class="ltr-value">{{ $row['last_activity_date'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['last_activity_date'])->format('Y/m/d') : '—' }}</td></tr>
        @empty<tr><td colspan="4"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse
    </tbody></table></div>
</section>
@endif
@endsection
