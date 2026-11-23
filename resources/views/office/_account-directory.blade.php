<x-page-header :title="__('navigation.accounts')" subtitle="جستجو، مشاهده گردش و ثبت دریافت یا پرداخت اشخاص" />

<nav class="workspace-tabs office-wide-tabs" aria-label="نقش حساب">
    <a class="workspace-tab {{ !$roleFilter ? 'active' : '' }}" href="{{ route('office.accounts', array_filter(['search' => request('search')])) }}">همه</a>
    @foreach([[\App\Enums\PartnerRole::Customer,'مشتریان'],[\App\Enums\PartnerRole::Supplier,'عرضه‌کنندگان'],[\App\Enums\PartnerRole::Saraf,'صراف‌ها']] as [$filterRole, $label])
        @if($canViewMoneyDetails || $filterRole !== \App\Enums\PartnerRole::Saraf)<a class="workspace-tab {{ $roleFilter === $filterRole->value ? 'active' : '' }}" href="{{ route('office.accounts', array_filter(['role' => $filterRole->value, 'search' => request('search')])) }}">{{ $label }}</a>@endif
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

<section class="cardx card-pad">
    <div class="section-head"><div><div class="section-title">فهرست حساب‌ها</div><div class="card-sub">یک شخص را برای مشاهده جزئیات انتخاب کنید.</div></div></div>
    <div class="table-wrap"><table class="tablex"><thead><tr><th>شخص</th><th>مرجع</th><th>نقش‌ها</th>@if($canViewMoneyDetails)<th>بیلانس</th><th>آخرین فعالیت</th>@endif<th></th></tr></thead><tbody>
        @forelse($partnerRows as $row)
            @php
                $partner = $row['partner'];
            @endphp
            <tr>
                <td><div class="td-main">{{ $partner->name }}</div><div class="td-sub ltr-value">{{ $partner->phone ?: '—' }}</div></td>
                <td class="ltr-value">{{ $partner->reference_code }}</td>
                <td><div class="role-badges">@foreach($partner->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach</div></td>
                @if($canViewMoneyDetails)<td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->absoluteMoney($row['balance']) }} USD</strong><div class="td-sub">{{ bccomp($row['balance'], '0', 2) > 0 ? 'دریافتنی' : (bccomp($row['balance'], '0', 2) < 0 ? 'پرداختنی' : 'تصفیه') }}</div></td><td class="ltr-value">{{ $row['last_activity'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['last_activity'])->format('Y/m/d') : '—' }}</td>@endif
                <td><a class="btnx btn-lightx btn-smx" aria-label="مشاهده حساب {{ $partner->name }}، {{ $partner->reference_code }}" href="{{ route('office.accounts', array_filter(['partner_id' => $partner->id, 'search' => request('search'), 'role' => request('role')])) }}">مشاهده</a></td>
            </tr>
        @empty<tr><td colspan="{{ $canViewMoneyDetails ? 6 : 4 }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse
    </tbody></table></div>
</section>
