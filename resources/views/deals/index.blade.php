@extends('layouts.app')

@section('title', __('deals.title'))

@section('content')
    <x-page-header :title="__('deals.title')" :subtitle="__('deals.subtitle')" />

    @if($errors->any())<div class="alertx alert-errorx"><i class="bi bi-exclamation-circle"></i><div>{{ __('common.validation_summary') }}</div></div>@endif

    @if($canCreate && $products->isEmpty())
        <section class="cardx card-pad">
            <div class="section-title">آماده‌سازی اولین معامله</div>
            <p>پیش از ثبت معامله، حداقل یک محصول فعال باید ثبت شود. برای تحویل مستقیم، ساختن ذخیره یا حساب نقدی لازم نیست. عرضه‌کننده و مشتری را می‌توانید در همین فورم ثبت کنید.</p>
            @if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN'))
                <a class="btnx btn-primaryx" href="{{ route('products.create') }}">ثبت اولین محصول</a>
            @else
                <p>از مدیر بخواهید محصول مورد نیاز را در تنظیمات ثبت کند؛ سپس این صفحه را دوباره باز کنید.</p>
            @endif
        </section>
    @elseif($canCreate)
        @include('deals._form')
    @endif

    <section class="cardx card-pad deal-list-card">
        <div class="section-head"><div><div class="section-title">{{ __('deals.title') }}</div><div class="card-sub">{{ __('deals.subtitle') }}</div></div></div>
        <form class="filters" method="GET" action="{{ route('deals.index') }}"><input class="control grow" name="search" value="{{ request('search') }}" placeholder="مرجع، عرضه‌کننده یا مشتری"><select class="control filter-control" name="product_id"><option value="">همه محصولات</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((int)request('product_id') === $product->id)>{{ $product->name }}</option>@endforeach</select><select class="control filter-control" name="status"><option value="">{{ __('deals.all_statuses') }}</option>@foreach(\App\Enums\DealStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach</select><button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button></form>
        @if($deals->isEmpty())<div class="empty"><i class="bi bi-arrow-left-right"></i><div class="empty-title">{{ __('deals.no_deals') }}</div></div>@else<div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('common.reference') }}</th><th>{{ __('deals.deal_date') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('deals.supplier') }}</th><th>{{ __('deals.customer') }}</th><th>{{ __('deals.final_quantity') }}</th>@if($canViewCommercialValues)<th>بهای منابع</th><th>فروش</th><th>نتیجه تأییدشده</th>@endif<th>{{ __('common.status') }}</th><th></th></tr></thead><tbody>@foreach($deals as $deal)<tr><td class="td-main">{{ $deal->reference_code }}</td><td class="ltr-value">@jalali($deal->deal_date)</td><td>{{ $deal->product?->name ?: '—' }}</td><td>{{ $deal->sources->map(fn ($line) => $line->supplier?->name ?: $line->warehouse?->name)->filter()->unique()->implode('، ') ?: '—' }}</td><td>{{ $deal->destinations->map(fn ($line) => $line->customer?->name ?: $line->warehouse?->name)->filter()->unique()->implode('، ') ?: '—' }}</td><td class="ltr-value">{{ $deal->total_delivered_quantity !== null ? app(\App\Support\NumberFormat::class)->weight($deal->total_delivered_quantity).' kg' : '—' }}</td>@if($canViewCommercialValues)<td class="ltr-value">{{ $deal->total_source_cost !== null ? app(\App\Support\NumberFormat::class)->money($deal->total_source_cost) : '—' }}</td><td class="ltr-value">{{ $deal->total_delivered_quantity !== null ? app(\App\Support\NumberFormat::class)->money($deal->total_destination_amount) : '—' }}</td><td class="ltr-value">{{ $deal->latestConfirmedSettlement ? app(\App\Support\NumberFormat::class)->money($deal->latestConfirmedSettlement->final_result_usd) : '—' }}</td>@endif<td><span class="badge {{ match($deal->status) { \App\Enums\DealStatus::Completed => 'badge-green', \App\Enums\DealStatus::InProgress => 'badge-blue', \App\Enums\DealStatus::Cancelled => 'badge-red', default => 'badge-amber' } }}">{{ $deal->status->label() }}</span></td><td><a class="table-link" href="{{ route('deals.show', $deal) }}">{{ __('common.view') }}</a></td></tr>@endforeach</tbody></table></div>{{ $deals->links() }}@endif
    </section>
@endsection
