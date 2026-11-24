<form method="get" action="{{ route('reports.accounts') }}" class="filters">
    <label class="field grow"><span>{{ __('finance.partner') }}</span>
        <select name="partner_id" class="control">
            <option value="">{{ __('reports.all_partners') }}</option>
            @foreach($partners as $partner)
                <option value="{{ $partner->id }}" @selected($selected?->is($partner))>{{ $partner->name }} · {{ $partner->reference_code }}</option>
            @endforeach
        </select>
    </label>
    @isset($opening)
        @foreach(['date_from', 'date_to'] as $bound)
            <label class="field"><span>{{ __('reports.'.$bound) }}</span>
                <input class="control ltr-value" name="{{ $bound }}" value="{{ $dateInputs[$bound] ?? '' }}" placeholder="۱۴۰۵/۰۱/۰۱">
            </label>
        @endforeach
    @endisset
    <button class="btnx btn-primaryx">{{ __('common.view') }}</button>
</form>
