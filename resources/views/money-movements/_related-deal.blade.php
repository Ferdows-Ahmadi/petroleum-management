@php
    $contextDeal = $deal ?? null;
    $selectedDealId = (int) $moneyOld('deal_id', $movement?->deal_id ?? $contextDeal?->id);
@endphp
<div class="field" @unless($contextDeal) data-related-deal-picker data-endpoint="{{ route('money-movements.deals') }}" data-partner="{{ $counterparty?->id }}" data-has-more="{{ (int) $dealOptions->hasMorePages() }}" @endunless>
    <label for="related-deal">{{ __('finance.related_deal') }}</label>
    @unless($contextDeal)
        <input class="control" type="search" maxlength="100" data-deal-search hidden
               aria-label="جستجوی معامله" placeholder="جستجو با نام شخص، محصول یا مرجع معامله">
    @endunless
    <select class="control" id="related-deal" name="deal_id">
        <option value="">{{ __('finance.unallocated') }}</option>
        @foreach($contextDeal ? [$contextDeal] : $dealOptions as $item)
            <option value="{{ $item->id }}" @selected($selectedDealId === $item->id)>{{ \App\Support\RelatedDeals::label($item) }}</option>
        @endforeach
    </select>
    @if($explicitRoute ?? false)
        <p class="field-hint" data-selected-deal aria-live="polite"></p>
    @endif
    @unless($contextDeal)
        <button class="btnx btn-lightx" type="button" data-more-deals hidden>معامله‌های بیشتر</button>
        <span class="field-hint" role="status" data-deal-status></span>
    @endunless
    @if($restoreMovementInput)<x-field-error name="deal_id" />@endif
</div>
