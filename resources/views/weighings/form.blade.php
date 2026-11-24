@extends('layouts.app')

@section('title', $weighingRecord->exists ? __('weighings.edit') : __('weighings.create'))

@section('content')
    <x-page-header :title="$weighingRecord->exists ? __('weighings.edit') : __('weighings.create')" :subtitle="__('weighings.subtitle')" />


    <form method="POST" enctype="multipart/form-data" action="{{ $weighingRecord->exists ? route('weighings.update', $weighingRecord) : route('weighings.store') }}" data-weighing-form data-mode-scope>
        @csrf
        @if($weighingRecord->exists)@method('PUT')@endif
        <div class="detail-grid">
            <div>
                <section class="cardx card-pad">
                    <div class="form-grid">
                        <div class="field span-2">
                            <label for="sale_item_id">{{ __('weighings.sale_item') }}</label>
                            <select id="sale_item_id" class="control" name="sale_item_id" required @disabled($weighingRecord->exists)>
                                <option value="">—</option>
                                @foreach($saleItems as $item)
                                    <option value="{{ $item->id }}" @selected((string) old('sale_item_id', $selectedSaleItemId) === (string) $item->id)>{{ $item->sale->customer->name }} · {{ $item->product->name }} · {{ $item->warehouse->name }} · {{ $item->sale->reference_code }}</option>
                                @endforeach
                            </select>
                            <x-field-error name="sale_item_id" />
                            @if(!$weighingRecord->exists && $saleItems->isEmpty())<div class="field-hint">{{ __('weighings.no_available_sale_items') }}</div>@endif
                        </div>
                        <div class="field"><label for="weighing_at">{{ __('weighings.weighing_at') }}</label><input id="weighing_at" class="control ltr-value" name="weighing_at" value="{{ old('weighing_at', $weighingRecord->weighing_at ? app(\App\Support\JalaliDate::class)->format($weighingRecord->weighing_at).' '.$weighingRecord->weighing_at->format('H:i') : '') }}" placeholder="1405/05/19 14:30" required><x-field-error name="weighing_at" /></div>
                        <div class="field"><label for="weighing_mode">{{ __('weighings.weighing_mode') }}</label><select id="weighing_mode" class="control" name="weighing_mode" data-weighing-mode><option value="DIRECT_NET" @selected(old('weighing_mode', $weighingRecord->weighing_mode?->value) === 'DIRECT_NET')>{{ __('weighings.mode.DIRECT_NET') }}</option><option value="TWO_STAGE" @selected(old('weighing_mode', $weighingRecord->weighing_mode?->value ?? 'TWO_STAGE') === 'TWO_STAGE')>{{ __('weighings.mode.TWO_STAGE') }}</option></select></div>
                        <div class="field"><label for="ticket_number">{{ __('weighings.ticket_number') }}</label><input id="ticket_number" class="control" name="ticket_number" value="{{ old('ticket_number', $weighingRecord->ticket_number) }}" maxlength="100"><x-field-error name="ticket_number" /></div>
                        <div class="field"><label for="station_name">{{ __('weighings.station_name') }}</label><input id="station_name" class="control" name="station_name" value="{{ old('station_name', $weighingRecord->station_name) }}" maxlength="150"><x-field-error name="station_name" /></div>
                        <div class="field"><label for="vehicle_number">{{ __('weighings.vehicle_number') }}</label><input id="vehicle_number" class="control" name="vehicle_number" value="{{ old('vehicle_number', $weighingRecord->vehicle_number) }}" maxlength="100"><x-field-error name="vehicle_number" /></div>
                        <div class="field"><label for="driver_name">{{ __('weighings.driver_name') }}</label><input id="driver_name" class="control" name="driver_name" value="{{ old('driver_name', $weighingRecord->driver_name) }}" maxlength="150"><x-field-error name="driver_name" /></div>
                        <div class="field" data-direct-fields><label for="net_weight_kg">{{ __('weighings.net_weight_kg') }}</label><input id="net_weight_kg" class="control ltr-value" name="net_weight_kg" value="{{ old('net_weight_kg', $weighingRecord->weighing_mode?->value === 'DIRECT_NET' ? $weighingRecord->net_weight_kg : null) }}" inputmode="decimal"><x-field-error name="net_weight_kg" /></div>
                        <div class="field" data-two-stage-fields><label for="first_weight_kg">{{ __('weighings.first_weight_kg') }}</label><input id="first_weight_kg" class="control ltr-value" name="first_weight_kg" value="{{ old('first_weight_kg', $weighingRecord->first_weight_kg) }}" inputmode="decimal" data-first-weight><x-field-error name="first_weight_kg" /><div class="field-hint">{{ __('weighings.first_weight_help') }}</div></div>
                        <div class="field" data-two-stage-fields><label for="second_weight_kg">{{ __('weighings.second_weight_kg') }}</label><input id="second_weight_kg" class="control ltr-value" name="second_weight_kg" value="{{ old('second_weight_kg', $weighingRecord->second_weight_kg) }}" inputmode="decimal" data-second-weight><x-field-error name="second_weight_kg" /><div class="field-hint">{{ __('weighings.second_weight_help') }}</div></div>
                        <div class="field span-2"><label for="ticket_image">{{ __('weighings.ticket_image') }}</label><input id="ticket_image" class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"><x-field-error name="ticket_image" /><div class="field-hint">{{ __('weighings.ticket_image_help') }}</div></div>
                        <div class="field span-2"><label for="notes">{{ __('common.notes') }}</label><textarea id="notes" class="control textarea" name="notes">{{ old('notes', $weighingRecord->notes) }}</textarea><x-field-error name="notes" /></div>
                    </div>
                </section>
            </div>
            <aside>
                <section class="cardx card-pad sticky-summary">
                    <div class="card-title">{{ __('weighings.calculation') }}</div>
                    <div class="calc-box">
                        <div class="calc-line"><span>{{ __('weighings.first_weight_kg') }}</span><strong class="ltr-value" data-first-preview>0.000 kg</strong></div>
                        <div class="calc-line"><span>{{ __('weighings.second_weight_kg') }}</span><strong class="ltr-value" data-second-preview>0.000 kg</strong></div>
                        <div class="calc-line"><span>{{ __('weighings.net_weight_kg') }}</span><strong class="calc-total ltr-value" data-net-preview>0.000 kg</strong></div>
                    </div>
                    <div class="card-sub">{{ __('weighings.net_formula') }}</div>
                </section>
            </aside>
        </div>
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit" @disabled(!$weighingRecord->exists && $saleItems->isEmpty())><i class="bi bi-check2"></i>ثبت وزن</button><a class="btnx btn-lightx" href="{{ $weighingRecord->exists ? route('weighings.show', $weighingRecord) : route('weighings.index') }}">{{ __('common.cancel') }}</a></div>
    </form>
@endsection
