@extends('layouts.app')
@section('title', __('common.edit').' · '.$editingDeal->reference_code)
@section('content')
<x-page-header :title="__('common.edit').' · '.$editingDeal->reference_code" :subtitle="__('deals.subtitle')">
    <a class="btnx btn-lightx" href="{{ route('deals.show', $editingDeal) }}">{{ __('common.back') }}</a>
</x-page-header>
@include('deals._form')
@endsection
