@php
    $restoreMovementInput ??= true;
    $moneyOld = fn (string $field, mixed $default = null): mixed => $restoreMovementInput ? old($field, $default) : $default;
    $movement = $moneyMovement ?? null;
    $selectedType = $moneyOld('movement_type', $movement?->movement_type?->value ?? ($defaultType ?? 'CUSTOMER_RECEIPT'));
    $selectedRoute = $moneyOld('money_route', $movement ? ($movement->saraf_id ? 'saraf' : 'account') : ($sarafs->isNotEmpty() ? 'saraf' : 'account'));
    $contextParty = isset($deal) && $deal ? ($selectedType === 'SUPPLIER_PAYMENT' ? $deal->sources->first()?->supplier_id : $deal->destinations->first()?->customer_id) : null;
@endphp
<x-operation-key :preserve="$restoreMovementInput" />
<div class="office-form-grid compact-grid" data-money-movement-fields @if(isset($deal) && $deal) data-customer-id="{{ $deal->destinations->first()?->customer_id }}" data-supplier-id="{{ $deal->sources->first()?->supplier_id }}" @endif>
    <div class="field"><label>{{ __('finance.transaction_date') }}</label><input class="control ltr-value" name="movement_date" value="{{ $moneyOld('movement_date', $movement?->movement_date ? app(\App\Support\JalaliDate::class)->format($movement->movement_date) : app(\App\Support\JalaliDate::class)->format(now())) }}" required>@if($restoreMovementInput)<x-field-error name="movement_date" />@endif</div>
    <div class="field"><label>{{ __('finance.transaction_type') }}</label><select class="control" name="movement_type" required>@foreach($movementTypes as $type)<option value="{{ $type->value }}" @selected($selectedType === $type->value)>{{ $type->label() }}</option>@endforeach</select>@if($restoreMovementInput)<x-field-error name="movement_type" />@endif</div>
    @if(isset($counterparty) && $counterparty)
        <input type="hidden" name="counterparty_id" value="{{ $counterparty->id }}">
        @if($restoreMovementInput)<x-field-error name="counterparty_id" />@endif
    @else
        <x-party-combobox name="counterparty_id" :label="__('finance.partner')" role="CUSTOMER" :parties="$partners" :selected-id="$moneyOld('counterparty_id', $movement?->counterparty_id ?? $contextParty)" :restore-input="$restoreMovementInput" data-money-party-combo />
    @endif
    @include('money-movements._related-deal')
    <div class="field"><label>{{ __('finance.money_route') }}</label><select class="control" name="money_route" data-money-route><option value="saraf" @selected($selectedRoute === 'saraf')>{{ __('finance.through_saraf') }}</option><option value="account" @selected($selectedRoute === 'account')>{{ __('finance.through_account') }}</option></select>@if($restoreMovementInput)<x-field-error name="money_route" />@endif</div>
    <div data-saraf-field><x-party-combobox name="saraf_id" :label="__('finance.saraf')" role="SARAF" :parties="$partners" :selected-id="$moneyOld('saraf_id', $movement?->saraf_id)" :restore-input="$restoreMovementInput" /></div>
    <div class="field" data-account-field><label>{{ __('finance.financial_account') }}</label><select class="control" name="financial_account_id"><option value="">—</option>@foreach($accounts as $accountItem)<option value="{{ $accountItem->id }}" @selected((int) $moneyOld('financial_account_id', $movement?->financial_account_id) === $accountItem->id)>{{ $accountItem->name }} · {{ $accountItem->currency->value }}</option>@endforeach</select>@if($restoreMovementInput)<x-field-error name="financial_account_id" />@endif
        @if($accounts->isEmpty())
            <p class="field-hint">برای ثبت پول از مسیر حساب مالی، ابتدا حساب صندوق یا بانک با واحد پول درست ثبت شود. مسیر صراف تنها برای پول واقعاً مربوط به صراف است.</p>
            @if(auth()->user()->hasAnyRole('GENERAL_MANAGER'))
                <a href="{{ route('financial-accounts.create') }}">ثبت اولین حساب مالی</a>
            @else
                <p class="field-hint">از مدیر عمومی بخواهید حساب مالی مورد نیاز را ثبت کند.</p>
            @endif
        @endif
    </div>
    <div class="field"><label>{{ __('finance.currency') }}</label><select class="control ltr-value" name="currency" required>@foreach(\App\Enums\Currency::cases() as $currency)<option value="{{ $currency->value }}" @selected($moneyOld('currency', $movement?->currency?->value ?? 'USD') === $currency->value)>{{ $currency->value }}</option>@endforeach</select>@if($restoreMovementInput)<x-field-error name="currency" />@endif</div>
    <div class="field"><label>{{ __('finance.amount') }}</label><input class="control ltr-value" name="amount" value="{{ $moneyOld('amount', $movement?->amount) }}" inputmode="decimal" required>@if($restoreMovementInput)<x-field-error name="amount" />@endif</div>
    <div class="field"><label>{{ __('finance.exchange_rate') }} (AFN/USD)</label><input class="control ltr-value" name="exchange_rate_to_usd" value="{{ $moneyOld('exchange_rate_to_usd', $movement?->exchange_rate_to_usd) }}" inputmode="decimal">@if($restoreMovementInput)<x-field-error name="exchange_rate_to_usd" />@endif</div>
    <div class="field"><label>{{ __('common.reference') }}</label><input class="control" name="reference" value="{{ $moneyOld('reference', $movement?->reference) }}">@if($restoreMovementInput)<x-field-error name="reference" />@endif</div>
    <div class="field span-2"><label>{{ __('common.notes') }}</label><textarea class="control" name="notes">{{ $moneyOld('notes', $movement?->notes) }}</textarea>@if($restoreMovementInput)<x-field-error name="notes" />@endif</div>
</div>
