@extends('layouts.app')

@section('title', __('navigation.accounts'))

@section('content')
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
        <input class="control grow" name="search" value="{{ request('search') }}" placeholder="نام، شماره تماس یا مرجع حساب">
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
            <tr class="{{ $selectedPartner?->id === $partner->id ? 'selected-row' : '' }}">
                <td><div class="td-main">{{ $partner->name }}</div><div class="td-sub ltr-value">{{ $partner->phone ?: '—' }}</div></td>
                <td class="ltr-value">{{ $partner->reference_code }}</td>
                <td><div class="role-badges">@foreach($partner->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach</div></td>
                @if($canViewMoneyDetails)<td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->absoluteMoney($row['balance']) }} USD</strong><div class="td-sub">{{ bccomp($row['balance'], '0', 2) > 0 ? 'دریافتنی' : (bccomp($row['balance'], '0', 2) < 0 ? 'پرداختنی' : 'تصفیه') }}</div></td><td class="ltr-value">{{ $row['last_activity'] ? \Morilog\Jalali\Jalalian::fromDateTime($row['last_activity'])->format('Y/m/d') : '—' }}</td>@endif
                <td><a class="btnx btn-lightx btn-smx" href="{{ route('office.accounts', array_filter(['partner_id' => $partner->id, 'search' => request('search'), 'role' => request('role')])) }}">مشاهده</a></td>
            </tr>
        @empty<tr><td colspan="{{ $canViewMoneyDetails ? 6 : 4 }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse
    </tbody></table></div>
</section>

@if($selectedPartner)
    @php
        $companyOwes = bccomp($balance, '0', 2) < 0;
        $isSaraf = $selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Saraf);
        $movementTypes = collect();
        if ($selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Customer)) $movementTypes->push(\App\Enums\MoneyMovementType::CustomerReceipt);
        if ($selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Supplier)) $movementTypes->push(\App\Enums\MoneyMovementType::SupplierPayment);
    @endphp
    <section class="cardx card-pad office-history account-profile">
        <div class="section-head">
            <div><div class="section-title">{{ $selectedPartner->name }}</div><div class="role-badges">@foreach($selectedPartner->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach @unless($selectedPartner->is_active)<span class="badge badge-red">{{ __('common.inactive') }}</span>@endunless</div></div>
            <div class="head-actions">
                @if($canViewMoneyDetails)<div class="balance-callout {{ $companyOwes ? 'payable' : 'receivable' }}"><span>{{ bccomp($balance, '0', 2) === 0 ? __('finance.settled') : ($companyOwes ? __('finance.company_owes_partner') : __('finance.partner_owes_company')) }}</span><strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($balance) }} USD</strong></div>@endif
                @if($canEditPartner)<button class="btnx btn-lightx" type="button" data-toggle-account-edit><i class="bi bi-pencil"></i>{{ __('common.edit') }}</button>@endif
            </div>
        </div>
        <div class="account-contact">{{ $selectedPartner->phone ?: __('common.not_available') }} @if($selectedPartner->address) · {{ $selectedPartner->address }} @endif</div>
        @if($canEditPartner)
            <form class="account-inline-edit" method="POST" action="{{ route('partners.update', $selectedPartner) }}" hidden data-account-edit-form>
                @csrf @method('PUT')
                <input type="hidden" name="return_to" value="accounts">
                <div class="form-grid-3">
                    <div class="field"><label>{{ __('partners.name') }}</label><input class="control" name="name" value="{{ old('name', $selectedPartner->name) }}" required></div>
                    <div class="field"><label>{{ __('common.phone') }}</label><input class="control ltr-value" name="phone" value="{{ old('phone', $selectedPartner->phone) }}"></div>
                    <div class="field"><label>{{ __('common.address') }}</label><input class="control" name="address" value="{{ old('address', $selectedPartner->address) }}"></div>
                </div>
                <div class="inline-role-editor">
                    <span class="field-label">{{ __('partners.relationship') }}</span>
                    @foreach(\App\Enums\PartnerRole::cases() as $role)
                        <label><input type="checkbox" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, old('roles', $selectedPartner->roles->pluck('role.value')->all()), true))> {{ $role->label() }}</label>
                    @endforeach
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $selectedPartner->is_active))> {{ __('common.active') }}</label>
                </div>
                <div class="field"><label>{{ __('common.notes') }}</label><textarea class="control" name="notes" rows="2">{{ old('notes', $selectedPartner->notes) }}</textarea></div>
                <div class="form-actions"><button class="btnx btn-primaryx">{{ __('common.save') }}</button><button class="btnx btn-lightx" type="button" data-toggle-account-edit>{{ __('common.cancel') }}</button></div>
            </form>
        @endif
    </section>

    @if($canRecordMoney && $selectedPartner->is_active && $movementTypes->isNotEmpty())
        <details class="cardx card-pad office-money-actions" @if($errors->any()) open @endif><summary class="section-title">ثبت دریافت / پرداخت</summary><form method="POST" action="{{ route('money-movements.store') }}">@csrf<input type="hidden" name="confirm_now" value="1"><input type="hidden" name="return_to" value="accounts">@include('money-movements._fields', ['moneyMovement' => null, 'counterparty' => $selectedPartner])<div class="form-actions"><button class="btnx btn-primaryx">{{ __('office.confirm_now') }}</button></div></form></details>
    @endif

    @if($isSaraf && $canViewMoneyDetails)
        <section class="cardx card-pad office-history"><div class="section-head"><div><div class="section-title">گردش صراف</div><div class="card-sub">موقعیت دفتر با صراف: <strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($sarafBalance) }} USD</strong></div></div></div><div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>تفصیل</th><th>شخص مرتبط</th><th>معامله</th><th>دریافت</th><th>پرداخت</th><th>بیلانس</th></tr></thead><tbody>@forelse($sarafStatement as $entry)<tr><td class="ltr-value">@jalali($entry['date'])</td><td><a class="table-link" href="{{ route('money-movements.show', $entry['source']) }}">{{ __('finance.ledger_types.'.$entry['type']) }}</a><div class="td-sub">{{ $entry['reference'] }}</div></td><td>{{ $entry['related_party'] ?: '—' }}</td><td>{{ $entry['deal'] ?: '—' }}</td><td class="ltr-value">{{ $entry['incoming'] ? app(\App\Support\NumberFormat::class)->money($entry['incoming']) : '—' }}</td><td class="ltr-value">{{ $entry['outgoing'] ? app(\App\Support\NumberFormat::class)->money($entry['outgoing']) : '—' }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($entry['running_balance']) }} USD</td></tr>@empty<tr><td colspan="7"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div></section>
    @endif

    @if($canViewMoneyDetails)
        <section class="cardx card-pad office-history"><div class="section-head"><div><div class="section-title">گردش حساب</div><div class="card-sub">معاملات تجارتی و رویدادهای پولی در یک خط زمانی</div></div></div><div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>تفصیل</th><th>مقدار</th><th>نرخ فی تن</th><th>مرجع</th><th>بدهکار</th><th>بستانکار</th><th>بیلانس</th></tr></thead><tbody>@forelse($statement as $entry)@php
            $ledgerSource = $entry['source'];
            $quantity = $ledgerSource instanceof \App\Models\Sale ? $ledgerSource->total_final_quantity_kg : ($ledgerSource instanceof \App\Models\Purchase ? $ledgerSource->total_quantity_kg : ($ledgerSource instanceof \App\Models\Deal ? ($ledgerSource->destinations->first()?->final_quantity_kg ?? $ledgerSource->sources->first()?->quantity_kg) : null));
            $rate = ($ledgerSource instanceof \App\Models\Sale || $ledgerSource instanceof \App\Models\Purchase) && $ledgerSource->items->count() === 1 ? $ledgerSource->items->first()->rate_per_ton : ($ledgerSource instanceof \App\Models\Deal ? ($ledgerSource->destinations->first()?->rate_per_ton ?? $ledgerSource->sources->first()?->rate_per_ton) : null);
        @endphp<tr><td class="ltr-value">@jalali($entry['date'])</td><td>{{ __('finance.ledger_types.'.$entry['type']) }}@if(!empty($entry['related_party']))<div class="td-sub">{{ __('finance.saraf') }}: {{ $entry['related_party'] }}</div>@endif</td><td class="ltr-value">{{ $quantity !== null ? app(\App\Support\NumberFormat::class)->weight($quantity).' kg' : '—' }}</td><td class="ltr-value">{{ $rate !== null ? app(\App\Support\NumberFormat::class)->rate($rate).' USD' : '—' }}</td><td>@if($ledgerSource instanceof \App\Models\MoneyMovement)<a class="table-link" href="{{ route('money-movements.show', $ledgerSource) }}">{{ $entry['reference'] }}</a>@elseif($ledgerSource instanceof \App\Models\Deal)<a class="table-link" href="{{ route('deals.show', $ledgerSource) }}">{{ $entry['reference'] }}</a>@else{{ $entry['reference'] }}@endif</td><td class="ltr-value">{{ $entry['debit'] ? app(\App\Support\NumberFormat::class)->money($entry['debit']) : '—' }}</td><td class="ltr-value">{{ $entry['credit'] ? app(\App\Support\NumberFormat::class)->money($entry['credit']) : '—' }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->money($entry['running_balance']) }} USD</strong></td></tr>@empty<tr><td colspan="8"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div></section>
    @endif
@endif
@endsection
