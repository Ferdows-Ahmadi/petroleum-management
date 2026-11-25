@php
    $editingDeal = $editingDeal ?? null;
    $draftSource = $editingDeal?->sources->first();
    $draftDestination = $editingDeal?->destinations->first();
    $draftWeighing = $draftDestination?->weighingRecord;
@endphp
        <form id="new-deal" method="POST" enctype="multipart/form-data" action="{{ $editingDeal ? route('deals.update', $editingDeal) : route('deals.store') }}" data-deal-form data-mode-scope>
            @csrf <x-operation-key />
            @if($editingDeal) @method('PUT') @endif
            <div class="detail-grid deal-create-grid">
                <div class="stack">
                    <section class="cardx card-pad">
                        <div class="section-head"><div><div class="section-title">{{ $editingDeal ? __('common.edit') : __('deals.new') }}</div></div><span class="badge badge-blue">{{ __('trades.currency') }}: USD</span></div>
                        <div class="form-grid">
                            <div class="field"><label for="deal_date">تاریخ معامله (هجری شمسی)</label><input id="deal_date" class="control ltr-value" name="deal_date" value="{{ old('deal_date', $editingDeal ? app(\App\Support\JalaliDate::class)->format($editingDeal->deal_date) : app(\App\Support\JalaliDate::class)->format(now())) }}" required><x-field-error name="deal_date" /></div>
                            <div class="field">
                                <label for="product_id">{{ __('trades.product') }}</label>
                                <select id="product_id" class="control" name="product_id">
                                    <option value="">—</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->id }}" @selected((string) old('product_id', $editingDeal?->product_id ?? $defaultProductId) === (string) $product->id)>{{ $product->name }}</option>
                                    @endforeach
                                </select>
                                <x-field-error name="product_id" />
                            </div>
                            <div class="field deal-notes">
                                <label for="notes">یادداشت معامله</label>
                                <textarea id="notes" class="control" name="notes" rows="2" maxlength="3000" dir="auto">{{ old('notes', $editingDeal?->notes) }}</textarea>
                                <x-field-error name="notes" />
                            </div>
                        </div>
                    </section>

                    <div class="deal-parties-grid">
                        <section class="cardx card-pad deal-side-card">
                            <div class="section-title"><i class="bi bi-box-arrow-in-left"></i>خرید و دریافت</div>
                            <fieldset class="deal-route"><legend>دریافت تیل از</legend>
                                @foreach(['SUPPLIER' => 'عرضه‌کننده', 'STORAGE' => 'ذخیره'] as $value => $label)
                                    <label><input type="radio" name="source_type" value="{{ $value }}" @checked(old('source_type', $draftSource?->source_type?->value ?? 'SUPPLIER') === $value)> {{ $label }}</label>
                                @endforeach
                                <x-field-error name="source_type" />
                            </fieldset>
                            <div data-simple-source-party><x-party-combobox name="supplier_id" :label="__('deals.supplier')" role="SUPPLIER" :parties="$parties" :selected-id="old('supplier_id', $draftSource?->supplier_id)" :required="false" /></div>
                            <div class="field" data-simple-source-warehouse hidden><label for="source_warehouse_id">ذخیره برداشت</label><select id="source_warehouse_id" class="control" name="source_warehouse_id"><option value="">—</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) old('source_warehouse_id', $draftSource?->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select><x-field-error name="source_warehouse_id" /><div class="field-hint">بهای تیل از موجودی ذخیره محاسبه می‌شود. ذخیره باید موجودی کافی داشته باشد.</div>@if($warehouses->isEmpty())<div class="field-hint">از مدیر بخواهید ذخیره واقعی شرکت را ثبت کند. تحویل مستقیم به ذخیره نیاز ندارد.</div>@endif</div>
                            <div class="field"><label for="source_quantity_kg">مقدار دریافت (کیلوگرام)</label><input id="source_quantity_kg" class="control ltr-value" name="source_quantity_kg" value="{{ old('source_quantity_kg', $draftSource?->quantity_kg) }}" inputmode="decimal" data-deal-source-quantity><x-field-error name="source_quantity_kg" /></div>
                            <div class="field" data-simple-source-rate><label for="source_rate_per_ton">{{ __('deals.source_rate') }} (دالر)</label><input id="source_rate_per_ton" class="control ltr-value" name="source_rate_per_ton" value="{{ old('source_rate_per_ton', $draftSource?->rate_per_ton) }}" inputmode="decimal" data-deal-source-rate><x-field-error name="source_rate_per_ton" /></div>
                        </section>

                        <section class="cardx card-pad deal-side-card">
                            <div class="section-title"><i class="bi bi-box-arrow-right"></i>فروش و تحویل</div>
                            <fieldset class="deal-route"><legend>تحویل تیل به</legend>
                                @foreach(['CUSTOMER' => 'مشتری', 'STORAGE' => 'ذخیره'] as $value => $label)
                                    <label><input type="radio" name="destination_type" value="{{ $value }}" @checked(old('destination_type', $draftDestination?->destination_type?->value ?? 'CUSTOMER') === $value)> {{ $label }}</label>
                                @endforeach
                                <x-field-error name="destination_type" />
                            </fieldset>
                            <div data-simple-destination-party><x-party-combobox name="customer_id" :label="__('deals.customer')" role="CUSTOMER" :parties="$parties" :selected-id="old('customer_id', $draftDestination?->customer_id)" :required="false" /></div>
                            <div class="field" data-simple-destination-warehouse hidden><label for="destination_warehouse_id">ذخیره دریافت</label><select id="destination_warehouse_id" class="control" name="destination_warehouse_id"><option value="">—</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) old('destination_warehouse_id', $draftDestination?->warehouse_id) === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select><x-field-error name="destination_warehouse_id" /><div class="field-hint">اگر مقدار تحویل خالی باشد، مقدار دریافت استفاده می‌شود. تا تأیید تصفیه، موجودی ذخیره تغییر نمی‌کند.</div>@if($warehouses->isEmpty())<div class="field-hint">از مدیر بخواهید ذخیره واقعی شرکت را ثبت کند.</div>@endif</div>
                            <div class="field"><label for="final_quantity_kg">مقدار تحویل (کیلوگرام)</label><input id="final_quantity_kg" class="control ltr-value" name="final_quantity_kg" value="{{ old('final_quantity_kg', $draftDestination?->final_quantity_kg) }}" inputmode="decimal" data-deal-final-quantity><x-field-error name="final_quantity_kg" /></div>
                            <label class="partial-delivery">
                                <input type="checkbox" name="partial_delivery" value="1" @checked(old('partial_delivery'))>
                                <span>تحویل قسمی؛ باقی‌مانده بعداً تحویل می‌شود.</span>
                            </label>
                            <x-field-error name="partial_delivery" />
                            <div class="field" data-simple-destination-rate><label for="destination_rate_per_ton">{{ __('deals.destination_rate') }} (دالر)</label><input id="destination_rate_per_ton" class="control ltr-value" name="destination_rate_per_ton" value="{{ old('destination_rate_per_ton', $draftDestination?->rate_per_ton) }}" inputmode="decimal" data-deal-destination-rate><x-field-error name="destination_rate_per_ton" /></div>
                        </section>
                    </div>

                    @if($canWeigh)
                        <details class="cardx card-pad deal-weighing-card" @if($errors->hasAny(['weighing_mode', 'weighing_at', 'net_weight_kg', 'first_weight_kg', 'second_weight_kg', 'ticket_number', 'station_name', 'vehicle_number', 'driver_name', 'ticket_image']) || old('first_weight_kg', $draftWeighing?->first_weight_kg) || old('net_weight_kg', $draftWeighing?->net_weight_kg)) open @endif>
                            <summary><span><i class="bi bi-speedometer2"></i>{{ __('deals.weighing') }}</span><small>{{ __('deals.weighing_optional') }}</small></summary>
                            <div class="form-grid weighing-fields">
                                <div class="field"><label for="weighing_mode">{{ __('weighings.weighing_mode') }}</label><select id="weighing_mode" class="control" name="weighing_mode" data-weighing-mode><option value="DIRECT_NET" @selected(old('weighing_mode', $draftWeighing?->weighing_mode?->value ?? 'DIRECT_NET') === 'DIRECT_NET')>{{ __('weighings.mode.DIRECT_NET') }}</option><option value="TWO_STAGE" @selected(old('weighing_mode', $draftWeighing?->weighing_mode?->value ?? 'DIRECT_NET') === 'TWO_STAGE')>{{ __('weighings.mode.TWO_STAGE') }}</option></select><x-field-error name="weighing_mode" /></div>
                                <div class="field"><label for="weighing_at">{{ __('weighings.weighing_at') }}</label><input id="weighing_at" class="control ltr-value" name="weighing_at" value="{{ old('weighing_at', $draftWeighing ? app(\App\Support\JalaliDate::class)->format($draftWeighing->weighing_at).' '.$draftWeighing->weighing_at->format('H:i') : null) }}" placeholder="1405/06/08 10:30"><x-field-error name="weighing_at" /></div>
                                <div class="field" data-direct-fields><label for="net_weight_kg">{{ __('weighings.net_weight_kg') }}</label><input id="net_weight_kg" class="control ltr-value" name="net_weight_kg" value="{{ old('net_weight_kg', $draftWeighing?->net_weight_kg) }}" inputmode="decimal" data-deal-net-weight><x-field-error name="net_weight_kg" /></div>
                                <div class="field" data-two-stage-fields><label for="first_weight_kg">{{ __('weighings.first_weight_kg') }}</label><input id="first_weight_kg" class="control ltr-value" name="first_weight_kg" value="{{ old('first_weight_kg', $draftWeighing?->first_weight_kg) }}" inputmode="decimal" data-deal-first-weight><x-field-error name="first_weight_kg" /></div>
                                <div class="field" data-two-stage-fields><label for="second_weight_kg">{{ __('weighings.second_weight_kg') }}</label><input id="second_weight_kg" class="control ltr-value" name="second_weight_kg" value="{{ old('second_weight_kg', $draftWeighing?->second_weight_kg) }}" inputmode="decimal" data-deal-second-weight><x-field-error name="second_weight_kg" /></div>
                                <div class="field"><label for="ticket_number">{{ __('weighings.ticket_number') }}</label><input id="ticket_number" class="control" name="ticket_number" value="{{ old('ticket_number', $draftWeighing?->ticket_number) }}"><x-field-error name="ticket_number" /></div>
                                <div class="field"><label for="station_name">{{ __('weighings.station_name') }}</label><input id="station_name" class="control" name="station_name" value="{{ old('station_name', $draftWeighing?->station_name) }}"><x-field-error name="station_name" /></div>
                                <div class="field"><label for="vehicle_number">{{ __('weighings.vehicle_number') }}</label><input id="vehicle_number" class="control" name="vehicle_number" value="{{ old('vehicle_number', $draftWeighing?->vehicle_number) }}"><x-field-error name="vehicle_number" /></div>
                                <div class="field"><label for="driver_name">{{ __('weighings.driver_name') }}</label><input id="driver_name" class="control" name="driver_name" value="{{ old('driver_name', $draftWeighing?->driver_name) }}"><x-field-error name="driver_name" /></div>
                                <div class="field span-2"><label for="ticket_image">{{ __('weighings.ticket_image') }}</label><input id="ticket_image" class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"><x-field-error name="ticket_image" /><div class="field-hint">{{ __('weighings.private_ticket_notice') }}</div></div>
                            </div>
                        </details>
                    @endif

                    <details class="cardx card-pad compact-details" @if($errors->hasAny(['requested_delivery_date', 'requested_quantity_kg', 'source_reference_number', 'destination_reference_number']) || filled(old('requested_delivery_date', $editingDeal?->requested_delivery_date)) || filled(old('requested_quantity_kg', $editingDeal?->requested_quantity_kg)) || filled(old('source_reference_number', $draftSource?->reference_number)) || filled(old('destination_reference_number', $draftDestination?->reference_number))) open @endif>
                        <summary>جزئیات سفارش و شماره‌های مرجع</summary>
                        <div class="form-grid">
                            <div class="field"><label for="requested_delivery_date">{{ __('deals.delivery_date') }}</label><input id="requested_delivery_date" class="control ltr-value" name="requested_delivery_date" value="{{ old('requested_delivery_date', $editingDeal?->requested_delivery_date ? app(\App\Support\JalaliDate::class)->format($editingDeal->requested_delivery_date) : null) }}"><x-field-error name="requested_delivery_date" /></div>
                            <div class="field"><label for="requested_quantity_kg">{{ __('deals.requested_quantity') }} (kg)</label><input id="requested_quantity_kg" class="control ltr-value" name="requested_quantity_kg" value="{{ old('requested_quantity_kg', $editingDeal?->requested_quantity_kg) }}" inputmode="decimal"><x-field-error name="requested_quantity_kg" /></div>
                            <div class="field"><label for="source_reference_number">{{ __('deals.source_reference') }}</label><input id="source_reference_number" class="control" name="source_reference_number" value="{{ old('source_reference_number', $draftSource?->reference_number) }}"><x-field-error name="source_reference_number" /></div><div class="field"><label for="destination_reference_number">{{ __('deals.destination_reference') }}</label><input id="destination_reference_number" class="control" name="destination_reference_number" value="{{ old('destination_reference_number', $draftDestination?->reference_number) }}"><x-field-error name="destination_reference_number" /></div>
                        </div>
                    </details>
                </div>

                <aside class="stack">
                    <section class="cardx card-pad sticky-summary deal-summary">
                        <div class="card-title">{{ __('deals.summary') }}</div>
                        <div class="calc-line"><span>{{ __('deals.source_amount') }}</span><strong class="ltr-value" data-deal-source-amount>0.00 USD</strong></div>
                        <div class="calc-line"><span>{{ __('deals.destination_amount') }}</span><strong class="ltr-value" data-deal-destination-amount>0.00 USD</strong></div>
                        <div class="calc-line"><span>{{ __('deals.gross_margin') }}</span><strong class="calc-total ltr-value" data-deal-margin>0.00 USD</strong></div>
                        <div class="form-actions deal-actions">
                            <button class="btnx btn-primaryx" type="submit" name="submit_action" value="register"><i class="bi bi-check2-circle"></i>ثبت معامله</button>
                        </div>
                    </section>
                </aside>
            </div>
        </form>
