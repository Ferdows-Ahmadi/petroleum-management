@extends('layouts.app')

@section('title', __('products.title'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="__('products.title')" :subtitle="__('products.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('products.create') }}"><i class="bi bi-plus-lg"></i>{{ __('products.add') }}</a>@endif
    </x-page-header>

    <section class="cardx card-pad">
        @if($products->isEmpty())
            <div class="empty"><i class="bi bi-droplet"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap">
                <table class="tablex">
                    <thead><tr><th>{{ __('products.name') }}</th><th>{{ __('products.code') }}</th><th>{{ __('products.unit') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead>
                    <tbody>
                    @foreach($products as $product)
                        <tr>
                            <td class="td-main">{{ $product->name }}</td><td>{{ $product->code }}</td><td>{{ $product->unit }}</td>
                            <td><x-status-badge :active="$product->is_active" /></td>
                            <td>
                                @if($canManage)
                                    <div class="row-actions">
                                        <a href="{{ route('products.edit', $product) }}">{{ __('common.edit') }}</a>
                                        <form method="POST" action="{{ route('products.destroy', $product) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="link-danger" type="submit">{{ __('common.delete') }}</button></form>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $products->links() }}
        @endif
    </section>
@endsection
