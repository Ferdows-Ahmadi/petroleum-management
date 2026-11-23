<x-page-header :title="__('navigation.accounts')" subtitle="جستجو، مشاهده گردش و ثبت دریافت یا پرداخت اشخاص" />

<nav class="workspace-tabs office-wide-tabs" aria-label="نقش حساب">
    <a class="workspace-tab {{ !$roleFilter ? 'active' : '' }}" @if(!$roleFilter) aria-current="page" @endif href="{{ route('office.accounts', array_filter(['search' => request('search')])) }}">همه</a>
    @foreach([[\App\Enums\PartnerRole::Customer,'مشتریان'],[\App\Enums\PartnerRole::Supplier,'عرضه‌کنندگان'],[\App\Enums\PartnerRole::Saraf,'صراف‌ها']] as [$filterRole, $label])
        @if($canViewMoneyDetails || $filterRole !== \App\Enums\PartnerRole::Saraf)<a class="workspace-tab {{ $roleFilter === $filterRole->value ? 'active' : '' }}" @if($roleFilter === $filterRole->value) aria-current="page" @endif href="{{ route('office.accounts', array_filter(['role' => $filterRole->value, 'search' => request('search')])) }}">{{ $label }}</a>@endif
    @endforeach
    @if(auth()->user()->hasAnyRole('GENERAL_MANAGER','SYSTEM_ADMIN','ACCOUNTANT'))<a class="workspace-tab" href="{{ route('capital-partners.index') }}">سرمایه</a>@endif
</nav>

<section class="cardx card-pad toolbar-panel">
    <form class="filters" method="GET" action="{{ route('office.accounts') }}">
        <input class="control grow" aria-label="نام، شماره تماس یا مرجع حساب" name="search" value="{{ request('search') }}" placeholder="نام، شماره تماس یا مرجع حساب">
        @if($roleFilter)<input type="hidden" name="role" value="{{ $roleFilter }}">@endif
        <button class="btnx btn-primaryx"><i class="bi bi-search"></i>{{ __('common.search') }}</button>
        @if(request()->hasAny(['search','role']))<a class="btnx btn-lightx" href="{{ route('office.accounts') }}">پاک‌کردن</a>@endif
    </form>
</section>

<section class="cardx card-pad" aria-labelledby="account-directory-heading">
    <div class="section-head">
        <h2 class="section-title" id="account-directory-heading">فهرست حساب‌ها</h2>
        <span class="card-sub">{{ number_format($partnerRows->count()) }} حساب</span>
    </div>
    <div class="table-wrap ledger-scroll" role="region" aria-labelledby="account-directory-heading" tabindex="0">
        <table class="tablex ledger-table">
            <thead>
                <tr>
                    <th scope="col">شخص</th>
                    <th scope="col">مرجع</th>
                    <th scope="col">نقش‌ها</th>
                    @if($canViewMoneyDetails)
                        <th scope="col">بیلانس <span class="ledger-unit">USD</span></th>
                        <th scope="col">آخرین فعالیت</th>
                    @endif
                    <th scope="col">{{ __('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($partnerRows as $row)
                    @php
                        $partner = $row['partner'];
                        $profileUrl = route('office.accounts', array_filter(['partner_id' => $partner->id, 'search' => request('search'), 'role' => request('role')]));
                    @endphp
                    <tr>
                        <td>
                            <a class="table-link td-main" href="{{ $profileUrl }}">{{ $partner->name }}</a>
                            @if($partner->phone)<div class="td-sub"><bdi>{{ $partner->phone }}</bdi></div>@endif
                        </td>
                        <td class="ledger-number">{{ $partner->reference_code }}</td>
                        <td><div class="role-badges">@foreach($partner->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach</div></td>
                        @if($canViewMoneyDetails)
                            <td class="ledger-number">
                                <strong>{{ app(\App\Support\NumberFormat::class)->absoluteMoney($row['balance']) }} USD</strong>
                                <div class="td-sub" dir="rtl">{{ match (bccomp($row['balance'], '0', 2)) {
                                    1 => 'دریافتنی', -1 => 'پرداختنی', default => 'تصفیه'
                                } }}</div>
                            </td>
                            <td class="ledger-number">{{ $row['last_activity'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['last_activity'])->format('Y/m/d') : '—' }}</td>
                        @endif
                        <td><a class="table-link" aria-label="مشاهده حساب {{ $partner->name }}، {{ $partner->reference_code }}" href="{{ $profileUrl }}">مشاهده</a></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $canViewMoneyDetails ? 6 : 4 }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
