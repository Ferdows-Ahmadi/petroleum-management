@if(session('success'))
    <div class="alertx alert-successx" role="status"><i class="bi bi-check-circle"></i>{{ session('success') }}</div>
@endif
@if($recovery = session('operation_recovery'))
    <div class="alertx {{ empty($recovery['error']) ? 'alert-successx' : 'alert-errorx' }}" role="{{ empty($recovery['error']) ? 'status' : 'alert' }}" data-operation-recovery>
        <i class="bi {{ empty($recovery['error']) ? 'bi-check-circle' : 'bi-exclamation-circle' }}" aria-hidden="true"></i>
        <div>
            <strong>{{ $recovery['message'] }}</strong>
            <div class="form-actions">
                <a class="btnx btn-lightx" href="{{ $recovery['fresh'] }}">{{ __('common.operation_new') }}</a>
            </div>
        </div>
    </div>
@elseif($errors->any())
    @php
        $validationForm = match (true) {
            array_key_exists('movement_type', session()->getOldInput()) || request()->routeIs('money-movements.*') => 'money',
            request()->routeIs('expenses.*') || (request()->routeIs('finance.journal') && array_key_exists('transaction_date', session()->getOldInput())) => 'expense',
            request()->routeIs('deals.index', 'deals.edit') => 'deal',
            default => null,
        };
    @endphp
    <div class="alertx alert-errorx" role="alert" data-validation-summary>
        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
        <div>
            <strong>{{ __($validationForm ? 'common.validation_form.'.$validationForm : 'common.validation_summary') }}</strong>
            <ul class="alert-list">
                @foreach(array_unique($errors->all()) as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    </div>
@endif
