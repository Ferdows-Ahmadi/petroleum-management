@php
    $moneyInvalid = $errors->any() && old('return_to') === $origin && old('_method') !== 'PUT';
    $defaultType = collect($movementTypes)->first(fn ($type) => $type->value === request('direction'))?->value ?? collect($movementTypes)->first()->value;
@endphp
<details id="money-entry" class="cardx card-pad office-money-actions" @if(request('entry') === 'money' || $moneyInvalid) open @endif>
    <summary class="section-title">{{ __('finance.create_payment') }}</summary>
    <form method="POST" action="{{ route('money-movements.store') }}">
        @csrf
        <input type="hidden" name="return_to" value="{{ $origin }}">
        @include('money-movements._fields', ['moneyMovement' => null, 'explicitRoute' => true, 'restoreMovementInput' => $moneyInvalid])
        <div class="form-actions"><button class="btnx btn-primaryx" type="submit" name="confirm_now" value="1">{{ __('office.confirm_now') }}</button></div>
    </form>
</details>
