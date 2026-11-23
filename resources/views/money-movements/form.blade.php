@extends('layouts.app')

@section('title', __('finance.money_movement'))

@section('content')
    <x-page-header :title="__('finance.money_movement')" :subtitle="$moneyMovement->reference_code"><a class="btnx btn-lightx" href="{{ route('money-movements.show', $moneyMovement) }}">{{ __('common.back') }}</a></x-page-header>
    <form method="POST" action="{{ route('money-movements.update', $moneyMovement) }}">@csrf @method('PUT')<section class="cardx card-pad">@include('money-movements._fields', ['deal' => null, 'counterparty' => null])<div class="form-actions"><button class="btnx btn-primaryx">{{ __('common.save') }}</button></div></section></form>
@endsection
