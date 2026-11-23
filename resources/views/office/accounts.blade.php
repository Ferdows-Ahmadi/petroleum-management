@extends('layouts.app')

@section('title', $selectedPartner?->name ?? __('navigation.accounts'))

@section('content')
@if($selectedPartner)
    <x-page-header :title="$selectedPartner->name" :subtitle="$selectedPartner->reference_code">
        <a class="btnx btn-lightx" href="{{ route('office.accounts', request()->only(['search', 'role'])) }}"><i class="bi bi-arrow-right"></i>فهرست حساب‌ها</a>
    </x-page-header>
    @php
        $editingProfile = $errors->any() && old('_method') === 'PUT' && old('return_to') === 'accounts';
        $editingMovement = $errors->any() && old('return_to') === 'accounts' && ! $editingProfile;
        $profileInput = $editingProfile ? session()->getOldInput() : [];
        $companyOwes = bccomp($balance, '0', 2) < 0;
        $isSaraf = $selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Saraf);
        $canEnterPayment = $canRecordMoney && $selectedPartner->is_active;
        $movementTypes = collect();
        if ($selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Customer)) $movementTypes->push(\App\Enums\MoneyMovementType::CustomerReceipt);
        if ($selectedPartner->roles->contains('role', \App\Enums\PartnerRole::Supplier)) $movementTypes->push(\App\Enums\MoneyMovementType::SupplierPayment);
    @endphp
    <section class="cardx card-pad office-history account-profile" aria-labelledby="account-profile-heading">
        <div class="section-head">
            <div><h2 class="section-title" id="account-profile-heading">مشخصات حساب</h2><div class="role-badges">@foreach($selectedPartner->roles as $role)<span class="badge badge-gray">{{ $role->role->label() }}</span>@endforeach @unless($selectedPartner->is_active)<span class="badge badge-red">{{ __('common.inactive') }}</span>@endunless</div></div>
            <div class="head-actions">
                @if($canViewMoneyDetails)<div class="balance-callout {{ $companyOwes ? 'payable' : 'receivable' }}"><span>{{ bccomp($balance, '0', 2) === 0 ? __('finance.settled') : ($companyOwes ? __('finance.company_owes_partner') : __('finance.partner_owes_company')) }}</span><strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($balance) }} USD</strong></div>@endif
                @if($canEditPartner)<button class="btnx btn-lightx" type="button" aria-expanded="{{ $editingProfile ? 'true' : 'false' }}" aria-controls="account-edit" data-toggle-account-edit><i class="bi bi-pencil"></i>{{ __('common.edit') }}</button>@endif
            </div>
        </div>
        <div class="account-contact">{{ $selectedPartner->phone ?: __('common.not_available') }} @if($selectedPartner->address) · {{ $selectedPartner->address }} @endif</div>
        @if($canEditPartner)
            <form id="account-edit" class="account-inline-edit" method="POST" action="{{ route('partners.update', $selectedPartner) }}" @if(!$editingProfile) hidden @endif data-account-edit-form>
                @csrf @method('PUT')
                <input type="hidden" name="return_to" value="accounts">
                <div class="form-grid-3">
                    <div class="field"><label for="account-name">{{ __('partners.name') }}</label><input id="account-name" class="control" name="name" value="{{ $profileInput['name'] ?? $selectedPartner->name }}" required>@if($editingProfile)<x-field-error name="name" />@endif</div>
                    <div class="field"><label for="account-phone">{{ __('common.phone') }}</label><input class="control ltr-value" id="account-phone" name="phone" value="{{ $profileInput['phone'] ?? $selectedPartner->phone }}"><x-field-error id="account-phone" name="phone" /></div>
                    <div class="field"><label for="account-address">{{ __('common.address') }}</label><input class="control" id="account-address" name="address" value="{{ $profileInput['address'] ?? $selectedPartner->address }}"><x-field-error id="account-address" name="address" /></div>
                </div>
                <div class="inline-role-editor">
                    <span class="field-label">{{ __('partners.relationship') }}</span>
                    @foreach(\App\Enums\PartnerRole::cases() as $role)
                        <label><input type="checkbox" name="roles[]" value="{{ $role->value }}" @checked(in_array($role->value, ($editingProfile ? ($profileInput['roles'] ?? []) : $selectedPartner->roles->pluck('role.value')->all()), true))> {{ $role->label() }}</label>
                    @endforeach
                    <label><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($profileInput['is_active'] ?? $selectedPartner->is_active)> {{ __('common.active') }}</label>
                </div>
                @if($editingProfile)<x-field-error name="roles" />@endif
                <div class="field"><label for="account-notes">{{ __('common.notes') }}</label><textarea class="control" id="account-notes" name="notes" rows="2">{{ $profileInput['notes'] ?? $selectedPartner->notes }}</textarea><x-field-error id="account-notes" name="notes" /></div>
                <div class="form-actions"><button class="btnx btn-primaryx">{{ __('common.save') }}</button><button class="btnx btn-lightx" type="button" data-toggle-account-edit>{{ __('common.cancel') }}</button></div>
            </form>
        @endif
    </section>

    @if($canViewMoneyDetails)
        @include('money-movements._overview', [
            'totals' => $paymentSummary, 'profile' => true,
            'allowReceipt' => $canEnterPayment && $movementTypes->contains(\App\Enums\MoneyMovementType::CustomerReceipt),
            'allowPayment' => $canEnterPayment && $movementTypes->contains(\App\Enums\MoneyMovementType::SupplierPayment),
        ])
        @if($canEnterPayment && $movementTypes->isNotEmpty())
            @include('money-movements._partial-entry', ['origin' => 'accounts', 'counterparty' => $selectedPartner])
        @endif
        @include('money-movements._history', ['history' => $paymentHistory, 'profile' => true])
    @endif

    @if($isSaraf && $canViewMoneyDetails)
        <section class="cardx card-pad office-history"><div class="section-head"><div><div class="section-title">گردش صراف</div><div class="card-sub">موقعیت دفتر با صراف: <strong class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($sarafBalance) }} USD</strong></div></div></div><div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>تفصیل</th><th>شخص مرتبط</th><th>معامله</th><th>دریافت</th><th>پرداخت</th><th>بیلانس</th></tr></thead><tbody>@forelse($sarafStatement as $entry)<tr><td class="ltr-value">@jalali($entry['date'])</td><td><a class="table-link" href="{{ route('money-movements.show', $entry['source']) }}">{{ __('finance.ledger_types.'.$entry['type']) }}</a><div class="td-sub">{{ $entry['reference'] }}</div></td><td>{{ $entry['related_party'] ?: '—' }}</td><td>{{ $entry['deal'] ?: '—' }}</td><td class="ltr-value">{{ $entry['incoming'] ? app(\App\Support\NumberFormat::class)->money($entry['incoming']) : '—' }}</td><td class="ltr-value">{{ $entry['outgoing'] ? app(\App\Support\NumberFormat::class)->money($entry['outgoing']) : '—' }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($entry['running_balance']) }} USD</td></tr>@empty<tr><td colspan="7"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div></section>
    @endif

    @if($canViewMoneyDetails)
        <section class="cardx card-pad office-history" aria-labelledby="account-ledger-heading"><div class="section-head"><div><h2 class="section-title" id="account-ledger-heading">گردش حساب</h2><div class="card-sub">معاملات تجارتی و رویدادهای پولی در یک خط زمانی</div></div></div><div class="table-wrap"><table class="tablex"><thead><tr><th>تاریخ</th><th>تفصیل</th><th>مقدار</th><th>نرخ فی تن</th><th>مرجع</th><th>بدهکار</th><th>بستانکار</th><th>بیلانس</th></tr></thead><tbody>@forelse($statement as $entry)@php
            $ledgerSource = $entry['source'];
            $quantity = $ledgerSource instanceof \App\Models\Sale ? $ledgerSource->total_final_quantity_kg : ($ledgerSource instanceof \App\Models\Purchase ? $ledgerSource->total_quantity_kg : ($ledgerSource instanceof \App\Models\Deal ? ($ledgerSource->destinations->first()?->final_quantity_kg ?? $ledgerSource->sources->first()?->quantity_kg) : null));
            $rate = ($ledgerSource instanceof \App\Models\Sale || $ledgerSource instanceof \App\Models\Purchase) && $ledgerSource->items->count() === 1 ? $ledgerSource->items->first()->rate_per_ton : ($ledgerSource instanceof \App\Models\Deal ? ($ledgerSource->destinations->first()?->rate_per_ton ?? $ledgerSource->sources->first()?->rate_per_ton) : null);
        @endphp<tr><td class="ltr-value">@jalali($entry['date'])</td><td>{{ __('finance.ledger_types.'.$entry['type']) }}@if(!empty($entry['related_party']))<div class="td-sub">{{ __('finance.saraf') }}: {{ $entry['related_party'] }}</div>@endif</td><td class="ltr-value">{{ $quantity !== null ? app(\App\Support\NumberFormat::class)->weight($quantity).' kg' : '—' }}</td><td class="ltr-value">{{ $rate !== null ? app(\App\Support\NumberFormat::class)->rate($rate).' USD' : '—' }}</td><td>@if($ledgerSource instanceof \App\Models\MoneyMovement)<a class="table-link" href="{{ route('money-movements.show', $ledgerSource) }}">{{ $entry['reference'] }}</a>@elseif($ledgerSource instanceof \App\Models\Deal)<a class="table-link" href="{{ route('deals.show', $ledgerSource) }}">{{ $entry['reference'] }}</a>@else{{ $entry['reference'] }}@endif</td><td class="ltr-value">{{ $entry['debit'] ? app(\App\Support\NumberFormat::class)->money($entry['debit']) : '—' }}</td><td class="ltr-value">{{ $entry['credit'] ? app(\App\Support\NumberFormat::class)->money($entry['credit']) : '—' }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->money($entry['running_balance']) }} USD</strong></td></tr>@empty<tr><td colspan="8"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>@endforelse</tbody></table></div></section>
    @endif
@else
    @include('office._account-directory')
@endif
@endsection
