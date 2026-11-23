@extends('layouts.app')

@section('title', $sale->exists ? __('trades.sale.edit') : __('trades.sale.create'))

@section('content')
    @php
        $formItems = old('items', $sale->exists
            ? $sale->items->map(fn ($item) => $item->only(['product_id', 'warehouse_id', 'requested_quantity_kg', 'rate_per_ton']))->all()
            : [['product_id' => '', 'warehouse_id' => '', 'requested_quantity_kg' => '', 'rate_per_ton' => '']]);
        $selectedCurrency = old('currency', $sale->currency?->value ?? 'USD');
    @endphp
    <x-page-header :title="$sale->exists ? __('trades.sale.edit') : __('trades.sale.create')" :subtitle="__('trades.sale.subtitle')" />

    <div class="notice"><i class="bi bi-info-circle"></i><div><strong>{{ __('trades.requested_quantity_kg') }}</strong>{{ __('trades.weighing_quantity_note') }}</div></div>
    @if($errors->any())<div class="alertx alert-errorx"><i class="bi bi-exclamation-circle"></i><div>{{ __('common.validation_summary') }}</div></div>@endif

    <form method="POST" action="{{ $sale->exists ? route('sales.update', $sale) : route('sales.store') }}" data-trade-form>
        @csrf
        @if($sale->exists)@method('PUT')@endif
        <div class="detail-grid">
            <div class="stack">
                <section class="cardx card-pad">
                    <div class="form-grid">
                        <div class="field"><label for="sale_date">{{ __('trades.sale.date') }}</label><input id="sale_date" class="control ltr-value" name="sale_date" value="{{ old('sale_date', app(\App\Support\JalaliDate::class)->format($sale->sale_date)) }}" placeholder="1405/05/19" required><x-field-error name="sale_date" /><div class="field-hint">{{ __('trades.date_format_note') }}</div></div>
                        <div class="field"><label for="business_partner_id">{{ __('trades.sale.customer') }}</label><select id="business_partner_id" class="control" name="business_partner_id" required><option value="">—</option>@foreach($partners as $partner)<option value="{{ $partner->id }}" @selected((string) old('business_partner_id', $sale->business_partner_id) === (string) $partner->id)>{{ $partner->name }} · {{ $partner->reference_code }}</option>@endforeach</select><x-field-error name="business_partner_id" /></div>
                        <div class="field"><label for="reference_number">{{ __('trades.reference_number') }}</label><input id="reference_number" class="control" name="reference_number" value="{{ old('reference_number', $sale->reference_number) }}" maxlength="100"><x-field-error name="reference_number" /></div>
                        <div class="field"><label for="currency">{{ __('trades.currency') }}</label><select id="currency" class="control" name="currency" data-trade-currency>@foreach($currencies as $currency)<option value="{{ $currency->value }}" @selected($selectedCurrency === $currency->value)>{{ $currency->value }}</option>@endforeach</select><x-field-error name="currency" /></div>
                        <div class="field span-2"><label for="notes">{{ __('common.notes') }}</label><textarea id="notes" class="control textarea" name="notes">{{ old('notes', $sale->notes) }}</textarea><x-field-error name="notes" /></div>
                    </div>
                </section>

                <section class="cardx card-pad">
                    <div class="section-head"><div class="section-title">{{ __('trades.items') }}</div><button class="btnx btn-lightx" type="button" data-add-item><i class="bi bi-plus-lg"></i>{{ __('trades.add_item') }}</button></div>
                    <div class="trade-items" data-item-list>
                        @foreach($formItems as $index => $item)
                            <div class="trade-item" data-trade-item>
                                <div class="trade-item-head"><strong>#{{ $index + 1 }}</strong><button class="link-danger" type="button" data-remove-item>{{ __('trades.remove_item') }}</button></div>
                                <div class="form-grid trade-item-grid">
                                    <div class="field"><label>{{ __('trades.product') }}</label><select class="control" name="items[{{ $index }}][product_id]" required><option value="">—</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) ($item['product_id'] ?? '') === (string) $product->id)>{{ $product->name }}</option>@endforeach</select><x-field-error :name="'items.'.$index.'.product_id'" /></div>
                                    <div class="field"><label>{{ __('trades.warehouse') }}</label><select class="control" name="items[{{ $index }}][warehouse_id]" required><option value="">—</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) ($item['warehouse_id'] ?? '') === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select><x-field-error :name="'items.'.$index.'.warehouse_id'" /></div>
                                    <div class="field"><label>{{ __('trades.requested_quantity_kg') }}</label><input class="control ltr-value" name="items[{{ $index }}][requested_quantity_kg]" value="{{ $item['requested_quantity_kg'] ?? '' }}" inputmode="decimal"><x-field-error :name="'items.'.$index.'.requested_quantity_kg'" /></div>
                                    <div class="field"><label>{{ __('trades.rate_per_ton') }}</label><input class="control ltr-value" name="items[{{ $index }}][rate_per_ton]" value="{{ $item['rate_per_ton'] ?? '' }}" inputmode="decimal" data-rate required><x-field-error :name="'items.'.$index.'.rate_per_ton'" /></div>
                                </div>
                                <div class="item-amount">{{ __('trades.final_quantity_kg') }}: <strong>{{ __('trades.pending_weighing') }}</strong></div>
                            </div>
                        @endforeach
                    </div>
                    <x-field-error name="items" />
                </section>
            </div>
            <aside class="stack">
                <section class="cardx card-pad sticky-summary"><div class="card-title">{{ __('trades.total_amount') }}</div><div class="calc-total">{{ __('trades.pending_weighing') }}</div></section>
            </aside>
        </div>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit"><i class="bi bi-check2"></i>{{ __('trades.save_draft') }}</button><a class="btnx btn-lightx" href="{{ $sale->exists ? route('sales.show', $sale) : route('sales.index') }}">{{ __('common.cancel') }}</a></div>

        <template data-item-template>
            <div class="trade-item" data-trade-item>
                <div class="trade-item-head"><strong>#</strong><button class="link-danger" type="button" data-remove-item>{{ __('trades.remove_item') }}</button></div>
                <div class="form-grid trade-item-grid">
                    <div class="field"><label>{{ __('trades.product') }}</label><select class="control" name="items[__INDEX__][product_id]" required><option value="">—</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('trades.warehouse') }}</label><select class="control" name="items[__INDEX__][warehouse_id]" required><option value="">—</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('trades.requested_quantity_kg') }}</label><input class="control ltr-value" name="items[__INDEX__][requested_quantity_kg]" inputmode="decimal"></div>
                    <div class="field"><label>{{ __('trades.rate_per_ton') }}</label><input class="control ltr-value" name="items[__INDEX__][rate_per_ton]" inputmode="decimal" data-rate required></div>
                </div>
                <div class="item-amount">{{ __('trades.final_quantity_kg') }}: <strong>{{ __('trades.pending_weighing') }}</strong></div>
            </div>
        </template>
    </form>
@endsection
