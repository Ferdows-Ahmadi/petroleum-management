@extends('layouts.app')

@section('title', __('warehouses.title'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'WAREHOUSE_OFFICER'))
    <x-page-header :title="__('warehouses.title')" :subtitle="__('warehouses.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('warehouses.create') }}"><i class="bi bi-plus-lg"></i>{{ __('warehouses.add') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">
        @if($warehouses->isEmpty())
            <div class="empty"><i class="bi bi-building"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('warehouses.name') }}</th><th>{{ __('warehouses.location') }}</th><th>{{ __('warehouses.stock') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
            @foreach($warehouses as $warehouse)
                <tr><td><div class="td-main">{{ $warehouse->name }}</div><div class="td-sub">{{ $warehouse->reference_code }}</div></td><td>{{ $warehouse->location ?: __('common.not_available') }}</td><td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->weight($warehouse->calculated_stock_kg) }} kg</strong></td><td><x-status-badge :active="$warehouse->is_active" /></td><td><div class="row-actions"><a href="{{ route('warehouses.show', $warehouse) }}">{{ __('common.view') }}</a>@if($canManage)<a href="{{ route('warehouses.edit', $warehouse) }}">{{ __('common.edit') }}</a><form method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="link-danger" type="submit">{{ __('common.delete') }}</button></form>@endif</div></td></tr>
            @endforeach
            </tbody></table></div>
            {{ $warehouses->links() }}
        @endif
    </section>
@endsection
