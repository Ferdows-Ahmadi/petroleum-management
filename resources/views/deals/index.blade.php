@extends('layouts.app')

@section('title', __('deals.title'))

@section('content')
    <x-page-header :title="__('deals.title')" :subtitle="__('deals.subtitle')">
        @if($canCreate)
            <a class="btnx btn-lightx" href="#deal-ledger"><i class="bi bi-list-ul"></i>دفتر معاملات</a>
        @endif
    </x-page-header>

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

    <section class="cardx card-pad deal-list-card" id="deal-ledger">
        <div class="section-head">
            <h2 class="section-title" id="deal-ledger-title">دفتر معاملات</h2>
            @if($deals->isNotEmpty())
                <span class="card-sub">{{ $deals->firstItem() }} تا {{ $deals->lastItem() }} از {{ $deals->total() }} معامله</span>
            @endif
        </div>
        <form class="filters" method="GET" action="{{ route('deals.index') }}#deal-ledger">
            <div class="field grow">
                <label for="deal-search">{{ __('common.search') }}</label>
                <input class="control" type="search" id="deal-search" name="search" value="{{ request('search') }}" placeholder="مرجع، عرضه‌کننده یا مشتری">
            </div>
            <div class="field">
                <label for="deal-product">{{ __('trades.product') }}</label>
                <select class="control filter-control" id="deal-product" name="product_id">
                    <option value="">همه محصولات</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((int) request('product_id') === $product->id)>{{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label for="deal-status">وضعیت معامله</label>
                <select class="control filter-control" id="deal-status" name="status">
                    <option value="">{{ __('deals.all_statuses') }}</option>
                    @foreach(\App\Enums\DealStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
            @if(request()->anyFilled(['search', 'product_id', 'status']))
                <a class="table-link" href="{{ route('deals.index') }}#deal-ledger">پاک‌کردن فیلترها</a>
            @endif
        </form>

        @if($deals->isEmpty())
            <div class="empty">
                <i class="bi bi-arrow-left-right"></i>
                <div class="empty-title">{{ request()->anyFilled(['search', 'product_id', 'status']) ? 'معامله‌ای با این فیلترها یافت نشد.' : __('deals.no_deals') }}</div>
            </div>
        @else
            @inject('numbers', 'App\Support\NumberFormat')
            <div class="table-wrap ledger-scroll" role="region" aria-labelledby="deal-ledger-title" tabindex="0">
                <table class="tablex ledger-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('common.reference') }}</th>
                            <th scope="col">{{ __('common.date') }}</th>
                            <th scope="col">{{ __('trades.product') }}</th>
                            <th scope="col">{{ __('deals.supplier') }} / ذخیره</th>
                            @if($canViewCommercialValues)
                                <th scope="col">نرخ منبع <span class="ledger-unit">USD / تن</span></th>
                            @endif
                            <th scope="col">{{ __('deals.customer') }} / ذخیره</th>
                            @if($canViewCommercialValues)
                                <th scope="col">نرخ فروش <span class="ledger-unit">USD / تن</span></th>
                            @endif
                            <th scope="col">وزن تحویل <span class="ledger-unit">kg</span></th>
                            @if($canViewCommercialValues)
                                <th scope="col">{{ __('deals.source_cost') }} <span class="ledger-unit">USD</span></th>
                                <th scope="col">{{ __('deals.destination_amount') }} <span class="ledger-unit">USD</span></th>
                                <th scope="col">نتیجه تأییدشده <span class="ledger-unit">USD</span></th>
                            @endif
                            <th scope="col">وضعیت معامله</th>
                            @if($canViewPayments)
                                <th scope="col">{{ __('finance.settlement_state') }}</th>
                            @endif
                            <th scope="col">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deals as $deal)
                            <tr>
                                <th scope="row"><a class="table-link" href="{{ route('deals.show', $deal) }}"><bdi>{{ $deal->reference_code }}</bdi></a></th>
                                <td class="ledger-number">@jalali($deal->deal_date)</td>
                                <td>{{ $deal->product?->name ?: '—' }}</td>
                                <td>
                                    @forelse($deal->sources as $line)
                                        <div>{{ $line->supplier?->name ?: ($line->warehouse?->name ?: '—') }}</div>
                                    @empty — @endforelse
                                </td>
                                @if($canViewCommercialValues)
                                    <td class="ledger-number">
                                        @forelse($deal->sources as $line)
                                            <div>{{ $line->rate_per_ton !== null ? $numbers->rate($line->rate_per_ton) : '—' }}</div>
                                        @empty — @endforelse
                                    </td>
                                @endif
                                <td>
                                    @forelse($deal->destinations as $line)
                                        <div>{{ $line->customer?->name ?: ($line->warehouse?->name ?: '—') }}</div>
                                    @empty — @endforelse
                                </td>
                                @if($canViewCommercialValues)
                                    <td class="ledger-number">
                                        @forelse($deal->destinations as $line)
                                            <div>{{ $line->rate_per_ton !== null ? $numbers->rate($line->rate_per_ton) : '—' }}</div>
                                        @empty — @endforelse
                                    </td>
                                @endif
                                <td class="ledger-number">{{ $deal->total_delivered_quantity !== null ? $numbers->weight($deal->total_delivered_quantity) : '—' }}</td>
                                @if($canViewCommercialValues)
                                    <td class="ledger-number">{{ $deal->total_source_cost !== null ? $numbers->money($deal->total_source_cost) : '—' }}</td>
                                    <td class="ledger-number">{{ $deal->total_delivered_quantity !== null ? $numbers->money($deal->total_destination_amount) : '—' }}</td>
                                    <td class="ledger-number">{{ $deal->latestConfirmedSettlement ? $numbers->money($deal->latestConfirmedSettlement->final_result_usd) : '—' }}</td>
                                @endif
                                <td><span class="badge {{ match($deal->status) { \App\Enums\DealStatus::Completed => 'badge-green', \App\Enums\DealStatus::InProgress => 'badge-blue', \App\Enums\DealStatus::Cancelled => 'badge-red', default => 'badge-amber' } }}">{{ $deal->status->label() }}</span></td>
                                @if($canViewPayments)
                                    <td>{{ __('finance.settlement_states.'.$paymentStates[$deal->id]) }}</td>
                                @endif
                                <td><a class="table-link" href="{{ route('deals.show', $deal) }}" aria-label="{{ __('common.view') }} {{ $deal->reference_code }}">{{ __('common.view') }}</a> · <a class="table-link" href="{{ route('deals.show', $deal) }}#deal-notes" aria-label="یادداشت‌های {{ $deal->reference_code }}">یادداشت‌ها</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $deals->links() }}
        @endif
    </section>
@endsection
