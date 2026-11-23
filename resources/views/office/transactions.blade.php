@extends('layouts.app')

@section('title', __('office.transactions'))

@section('content')
    @php
        $today = app(\App\Support\JalaliDate::class)->format(now());
        $selectedProduct = old('product_id', $defaultProduct?->id);
        $selectedWarehouse = old('warehouse_id', $defaultWarehouse?->id);
        $canViewTicket = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER', 'TRANSPORT_OFFICER');
    @endphp
    <x-page-header :title="__('office.transactions')" :subtitle="__('office.transactions_subtitle')" />

    <nav class="workspace-tabs" aria-label="{{ __('office.transactions') }}">
        @if($canCreateTrade)<a class="workspace-tab {{ $tab === 'purchase' ? 'active' : '' }}" href="{{ route('office.transactions', ['tab' => 'purchase']) }}"><i class="bi bi-box-arrow-in-down"></i>{{ __('office.purchase_tab') }}</a>@endif
        <a class="workspace-tab {{ $tab === 'sale' ? 'active' : '' }}" href="{{ route('office.transactions', ['tab' => 'sale']) }}"><i class="bi bi-box-arrow-up"></i>{{ __('office.sale_tab') }}</a>
    </nav>

    @if($errors->any())
        <div class="alertx alert-errorx"><i class="bi bi-exclamation-circle"></i><div>{{ __('common.validation_summary') }}<ul class="alert-list">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    @if($tab === 'purchase' && $canCreateTrade)
        <form class="office-entry-form" method="POST" enctype="multipart/form-data" action="{{ route('office.transactions.purchases.store') }}" data-office-purchase-form data-partner-form>
            @csrf
            <section class="cardx card-pad">
                <div class="section-head"><div><div class="section-title">{{ __('trades.purchase.create') }}</div><div class="card-sub">{{ __('office.partner_search_help') }}</div></div></div>
                <div class="office-form-grid">
                    <div class="field"><label>{{ __('trades.purchase.date') }}</label><input class="control ltr-value" name="purchase_date" value="{{ old('purchase_date', $today) }}" placeholder="1405/05/19" required></div>
                    <div class="field"><label>{{ __('trades.purchase.supplier') }}</label><input class="control" name="partner_name" value="{{ old('partner_name') }}" list="office-partners-supplier" autocomplete="off" placeholder="{{ __('office.partner_search') }}" required data-partner-name><datalist id="office-partners-supplier">@foreach($partners as $partner)<option value="{{ $partner->name }}">{{ $partner->phone }}</option>@endforeach</datalist></div>
                    <div class="field"><label>{{ __('trades.product') }}</label><select class="control" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) $selectedProduct === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('trades.warehouse') }}</label><select class="control" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) $selectedWarehouse === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                    <div class="field"><label>{{ __('trades.quantity_kg') }}</label><input class="control ltr-value" name="quantity_kg" value="{{ old('quantity_kg') }}" inputmode="decimal" data-quantity required></div>
                    <div class="field"><label>{{ __('trades.rate_per_ton') }} (USD)</label><input class="control ltr-value" name="rate_per_ton" value="{{ old('rate_per_ton') }}" inputmode="decimal" data-rate required></div>
                </div>
                <div class="office-calculation"><span>{{ __('trades.total_amount') }}</span><strong class="ltr-value"><span data-trade-total>0.00</span> <span data-total-currency>USD</span></strong></div>
            </section>
            <details class="cardx card-pad office-optional"><summary>{{ __('office.more_details') }}</summary><div class="office-form-grid compact-grid"><div class="field" data-new-partner-field><label>{{ __('office.partner_phone') }}</label><input class="control ltr-value" name="partner_phone" value="{{ old('partner_phone') }}" maxlength="50"></div><div class="field"><label>{{ __('common.reference') }}</label><input class="control" name="reference_number" value="{{ old('reference_number') }}" maxlength="100"></div><div class="field"><label>{{ __('weighings.purchase_ticket_image') }}</label><input class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"></div><div class="field span-2"><label>{{ __('common.notes') }}</label><textarea class="control textarea" name="notes">{{ old('notes') }}</textarea></div></div></details>
            @if($canRecordFinance)
                <details class="cardx card-pad office-optional"><summary>{{ __('office.payment_optional') }}</summary><div class="office-form-grid compact-grid"><div class="field"><label>{{ __('office.paid_amount') }}</label><input class="control ltr-value" name="payment_amount" value="{{ old('payment_amount') }}" inputmode="decimal"></div><div class="field"><label>{{ __('finance.financial_account') }}</label><select class="control" name="financial_account_id"><option value="">—</option>@foreach($financialAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} · {{ $account->currency->value }}</option>@endforeach</select></div><div class="field"><label>{{ __('finance.exchange_rate') }} (AFN/USD)</label><input class="control ltr-value" name="exchange_rate_to_usd" value="{{ old('exchange_rate_to_usd') }}" inputmode="decimal"></div></div></details>
            @endif
            <div class="form-actions"><button class="btnx btn-primaryx" name="submit_action" value="confirm"><i class="bi bi-check2-circle"></i>{{ __('office.confirm_now') }}</button><button class="btnx btn-lightx" name="submit_action" value="draft"><i class="bi bi-clock"></i>{{ __('office.save_pending') }}</button></div>
        </form>

        <section class="cardx card-pad office-history">
            <div class="section-title">{{ __('office.recent_purchases') }}</div>
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('trades.purchase.date') }}</th><th>{{ __('trades.purchase.supplier') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('trades.quantity_kg') }}</th><th>{{ __('trades.total_amount') }}</th>@if($canViewPartnerAccounts)<th>{{ __('office.partner_balance') }}</th>@endif<th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
                @forelse($purchases as $purchase)
                    @php($partnerBalance = $partnerBalances->get($purchase->business_partner_id, '0.00'))
                    <tr><td class="ltr-value">@jalali($purchase->purchase_date)</td><td class="td-main">{{ $purchase->supplier->name }}</td><td>{{ $purchase->items->pluck('product.name')->join('، ') }}</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->weight($purchase->total_quantity_kg) }} kg</td><td class="ltr-value">{{ app(\App\Support\NumberFormat::class)->money($purchase->total_amount) }} USD</td>@if($canViewPartnerAccounts)<td><div class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($partnerBalance) }} USD</div><div class="td-sub">{{ bccomp($partnerBalance, '0', 2) < 0 ? __('finance.company_owes_partner') : (bccomp($partnerBalance, '0', 2) > 0 ? __('finance.partner_owes_company') : __('finance.settled')) }}</div></td>@endif<td><x-transaction-status-badge :status="$purchase->status" /></td><td><a class="section-link" href="{{ route('purchases.show', $purchase) }}">{{ __('common.view') }}</a></td></tr>
                @empty
                    <tr><td colspan="{{ $canViewPartnerAccounts ? 8 : 7 }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    @else
        @if($canCreateTrade)
            <form class="office-entry-form" method="POST" enctype="multipart/form-data" action="{{ route('office.transactions.sales.store') }}" data-office-sale-form data-partner-form>
                @csrf
                <section class="cardx card-pad">
                    <div class="section-head"><div><div class="section-title">{{ __('trades.sale.create') }}</div><div class="card-sub">{{ __('office.partner_search_help') }}</div></div></div>
                    <div class="office-form-grid">
                        <div class="field"><label>{{ __('trades.sale.date') }}</label><input class="control ltr-value" name="sale_date" value="{{ old('sale_date', $today) }}" placeholder="1405/05/19" required></div>
                        <div class="field"><label>{{ __('trades.sale.customer') }}</label><input class="control" name="partner_name" value="{{ old('partner_name') }}" list="office-partners-customer" autocomplete="off" placeholder="{{ __('office.partner_search') }}" required data-partner-name><datalist id="office-partners-customer">@foreach($partners as $partner)<option value="{{ $partner->name }}">{{ $partner->phone }}</option>@endforeach</datalist></div>
                        <div class="field"><label>{{ __('trades.product') }}</label><select class="control" name="product_id" required>@foreach($products as $product)<option value="{{ $product->id }}" @selected((string) $selectedProduct === (string) $product->id)>{{ $product->name }}</option>@endforeach</select></div>
                        <div class="field"><label>{{ __('trades.warehouse') }}</label><select class="control" name="warehouse_id" required>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) $selectedWarehouse === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
                        <div class="field"><label>{{ __('trades.requested_quantity_kg') }} · {{ __('common.optional') }}</label><input class="control ltr-value" name="requested_quantity_kg" value="{{ old('requested_quantity_kg') }}" inputmode="decimal"></div>
                        <div class="field"><label>{{ __('trades.rate_per_ton') }} (USD)</label><input class="control ltr-value" name="rate_per_ton" value="{{ old('rate_per_ton') }}" inputmode="decimal" data-office-rate required></div>
                    </div>
                    @if($canWeigh)
                        <div class="office-subsection" data-mode-scope>
                            <div class="section-title">{{ __('office.weighing_section') }}</div><div class="card-sub">{{ __('office.weighing_help') }}</div>
                            <div class="office-form-grid compact-grid"><div class="field"><label>{{ __('weighings.weighing_mode') }}</label><select class="control" name="weighing_mode" data-weighing-mode><option value="DIRECT_NET" @selected(old('weighing_mode') === 'DIRECT_NET')>{{ __('weighings.mode.DIRECT_NET') }}</option><option value="TWO_STAGE" @selected(old('weighing_mode', 'TWO_STAGE') === 'TWO_STAGE')>{{ __('weighings.mode.TWO_STAGE') }}</option></select></div><div class="field" data-direct-fields><label>{{ __('weighings.net_weight_kg') }}</label><input class="control ltr-value" name="net_weight_kg" value="{{ old('net_weight_kg') }}" data-office-direct-net inputmode="decimal"></div><div class="field" data-two-stage-fields><label>{{ __('weighings.first_weight_kg') }}</label><input class="control ltr-value" name="first_weight_kg" value="{{ old('first_weight_kg') }}" data-office-first inputmode="decimal"></div><div class="field" data-two-stage-fields><label>{{ __('weighings.second_weight_kg') }}</label><input class="control ltr-value" name="second_weight_kg" value="{{ old('second_weight_kg') }}" data-office-second inputmode="decimal"></div><div class="field"><label>{{ __('weighings.ticket_image') }}</label><input class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"></div></div>
                            <details class="office-optional"><summary>{{ __('office.more_details') }}</summary><div class="office-form-grid compact-grid"><div class="field"><label>{{ __('weighings.weighing_at') }}</label><input class="control ltr-value" name="weighing_at" value="{{ old('weighing_at', $today.' '.now()->format('H:i')) }}"></div><div class="field"><label>{{ __('weighings.ticket_number') }}</label><input class="control" name="ticket_number" value="{{ old('ticket_number') }}"></div><div class="field"><label>{{ __('weighings.station_name') }}</label><input class="control" name="station_name" value="{{ old('station_name') }}"></div><div class="field"><label>{{ __('weighings.vehicle_number') }}</label><input class="control" name="vehicle_number" value="{{ old('vehicle_number') }}"></div><div class="field"><label>{{ __('weighings.driver_name') }}</label><input class="control" name="driver_name" value="{{ old('driver_name') }}"></div></div></details>
                            <div class="office-calculation"><span>{{ __('weighings.net_weight_kg') }} / {{ __('trades.total_amount') }}</span><strong class="ltr-value"><span data-office-net>0 kg</span> · <span data-office-amount>0.00 USD</span></strong></div>
                        </div>
                    @endif
                </section>
                <details class="cardx card-pad office-optional"><summary>{{ __('office.more_details') }}</summary><div class="office-form-grid compact-grid"><div class="field" data-new-partner-field><label>{{ __('office.partner_phone') }}</label><input class="control ltr-value" name="partner_phone" value="{{ old('partner_phone') }}" maxlength="50"></div><div class="field"><label>{{ __('common.reference') }}</label><input class="control" name="reference_number" value="{{ old('reference_number') }}" maxlength="100"></div><div class="field span-2"><label>{{ __('common.notes') }}</label><textarea class="control textarea" name="notes">{{ old('notes') }}</textarea></div></div></details>
                @if($canRecordFinance)
                    <details class="cardx card-pad office-optional"><summary>{{ __('office.receipt_optional') }}</summary><div class="office-form-grid compact-grid"><div class="field"><label>{{ __('office.received_amount') }}</label><input class="control ltr-value" name="payment_amount" value="{{ old('payment_amount') }}" inputmode="decimal"></div><div class="field"><label>{{ __('finance.financial_account') }}</label><select class="control" name="financial_account_id"><option value="">—</option>@foreach($financialAccounts as $account)<option value="{{ $account->id }}">{{ $account->name }} · {{ $account->currency->value }}</option>@endforeach</select></div><div class="field"><label>{{ __('finance.exchange_rate') }} (AFN/USD)</label><input class="control ltr-value" name="exchange_rate_to_usd" value="{{ old('exchange_rate_to_usd') }}" inputmode="decimal"></div></div></details>
                @endif
                <div class="form-actions">@if($canWeigh)<button class="btnx btn-primaryx" name="submit_action" value="confirm"><i class="bi bi-check2-circle"></i>{{ __('office.confirm_now') }}</button>@endif<button class="btnx btn-lightx" name="submit_action" value="draft"><i class="bi bi-clock"></i>{{ __('office.save_pending') }}</button></div>
            </form>
        @endif

        <section class="cardx card-pad office-history">
            <div class="section-title">{{ __('office.recent_sales') }}</div>
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('trades.sale.date') }}</th><th>{{ __('trades.sale.customer') }}</th><th>{{ __('trades.product') }}</th><th>{{ __('trades.requested_quantity_kg') }}</th><th>{{ __('weighings.net_weight_kg') }}</th>@if($canViewTrade)<th>{{ __('trades.total_amount') }}</th>@endif @if($canViewPartnerAccounts)<th>{{ __('office.partner_balance') }}</th>@endif<th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
                @forelse($sales as $sale)
                    @php($item = $sale->items->first())
                    @php($weighing = $item?->weighingRecord)
                    @php($partnerBalance = $partnerBalances->get($sale->business_partner_id, '0.00'))
                    <tr>
                        <td class="ltr-value">@jalali($sale->sale_date)</td><td class="td-main">{{ $sale->customer->name }}</td><td>{{ $item?->product?->name }}</td><td class="ltr-value">{{ $item?->requested_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($item->requested_quantity_kg).' kg' : '—' }}</td><td class="ltr-value">{{ $item?->final_quantity_kg ? app(\App\Support\NumberFormat::class)->weight($item->final_quantity_kg).' kg' : '—' }}</td>
                        @if($canViewTrade)<td class="ltr-value">{{ $sale->status === \App\Enums\TransactionStatus::Confirmed ? app(\App\Support\NumberFormat::class)->money($sale->total_amount).' USD' : '—' }}</td>@endif
                        @if($canViewPartnerAccounts)<td><div class="ltr-value">{{ app(\App\Support\NumberFormat::class)->absoluteMoney($partnerBalance) }} USD</div><div class="td-sub">{{ bccomp($partnerBalance, '0', 2) < 0 ? __('finance.company_owes_partner') : (bccomp($partnerBalance, '0', 2) > 0 ? __('finance.partner_owes_company') : __('finance.settled')) }}</div></td>@endif
                        <td>@if($sale->status === \App\Enums\TransactionStatus::Confirmed)<x-transaction-status-badge :status="$sale->status" />@elseif(!$weighing)<span class="badge badge-gray">{{ __('office.waiting_weighing') }}</span>@elseif($weighing->status === \App\Enums\WeighingStatus::Draft)<span class="badge badge-amber">{{ __('office.waiting_second_weight') }}</span>@else<span class="badge badge-blue">{{ __('office.ready_confirmation') }}</span>@endif</td>
                        <td><div class="row-actions">
                            @if($canViewTrade)<a class="section-link" href="{{ route('sales.show', $sale) }}">{{ __('common.view') }}</a>@endif
                            @if($canViewTicket && $weighing?->ticket_image_path)<a class="section-link" href="{{ route('weighings.ticket', $weighing) }}" target="_blank" rel="noopener">{{ __('weighings.view_ticket') }}</a>@endif
                            @if($canWeigh && $sale->status === \App\Enums\TransactionStatus::Draft && !$weighing)<details class="inline-action"><summary>{{ __('office.record_first_weight') }}</summary><form method="POST" enctype="multipart/form-data" action="{{ route('office.transactions.sales.weighing.store', $sale) }}" data-mode-scope>@csrf<input type="hidden" name="weighing_at" value="{{ $today.' '.now()->format('H:i') }}"><select class="control" name="weighing_mode" data-weighing-mode><option value="DIRECT_NET">{{ __('weighings.mode.DIRECT_NET') }}</option><option value="TWO_STAGE">{{ __('weighings.mode.TWO_STAGE') }}</option></select><input class="control ltr-value" name="net_weight_kg" placeholder="{{ __('weighings.net_weight_kg') }}" data-direct-fields><input class="control ltr-value" name="first_weight_kg" placeholder="{{ __('weighings.first_weight_kg') }}" data-two-stage-fields><input class="control ltr-value" name="second_weight_kg" placeholder="{{ __('weighings.second_weight_kg') }}" data-two-stage-fields><input class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"><button class="btnx btn-primaryx">{{ __('common.save') }}</button></form></details>@elseif($canWeigh && $weighing?->status === \App\Enums\WeighingStatus::Draft)<form class="inline-complete" method="POST" enctype="multipart/form-data" action="{{ route('office.transactions.sales.weighing.complete', $sale) }}">@csrf<input class="control ltr-value" name="second_weight_kg" placeholder="{{ __('weighings.second_weight_kg') }}" required><input class="control" type="file" name="ticket_image" accept="image/jpeg,image/png,image/webp"><button class="btnx btn-primaryx">{{ __('office.complete_second_weight') }}</button></form>@elseif($canCreateTrade && $sale->status === \App\Enums\TransactionStatus::Draft && $weighing?->status === \App\Enums\WeighingStatus::Finalized)<form method="POST" action="{{ route('sales.confirm', $sale) }}" data-confirm="{{ __('trades.confirm_question') }}">@csrf<button class="btnx btn-primaryx">{{ __('office.confirm_sale') }}</button></form>@endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 7 + ($canViewTrade ? 1 : 0) + ($canViewPartnerAccounts ? 1 : 0) }}"><div class="empty compact">{{ __('common.no_records') }}</div></td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    @endif
@endsection
