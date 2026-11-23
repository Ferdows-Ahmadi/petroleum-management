@extends('layouts.app')

@section('title', __('weighings.title'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'TRANSPORT_OFFICER'))
    <x-page-header :title="__('weighings.title')" :subtitle="__('weighings.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('weighings.create') }}"><i class="bi bi-plus-lg"></i>{{ __('weighings.create') }}</a>@endif
    </x-page-header>

    <section class="cardx card-pad">
        <form class="filters" method="GET">
            <input class="control grow" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}...">
            <select class="control filter-control" name="status">
                <option value="">{{ __('common.all_statuses') }}</option>
                @foreach(\App\Enums\WeighingStatus::cases() as $status)<option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>@endforeach
            </select>
            <button class="btnx btn-lightx"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>

        @if($weighings->isEmpty())
            <div class="empty"><i class="bi bi-speedometer"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr>
                <th>{{ __('weighings.ticket_number') }}</th><th>{{ __('weighings.weighing_at') }}</th><th>{{ __('weighings.weighing_mode') }}</th><th>{{ __('weighings.vehicle_number') }}</th><th>{{ __('weighings.first_weight_kg') }}</th><th>{{ __('weighings.second_weight_kg') }}</th><th>{{ __('weighings.net_weight_kg') }}</th><th>{{ __('weighings.sale_reference') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th>
            </tr></thead><tbody>
            @foreach($weighings as $record)
                <tr>
                    <td class="td-main">{{ $record->reference_code }}</td>
                    <td class="ltr-value">@jalaliDateTime($record->weighing_at)</td>
                    <td>{{ $record->weighing_mode->label() }}</td>
                    <td>{{ $record->vehicle_number ?: __('common.not_available') }}</td>
                    <td class="ltr-value">{{ $record->first_weight_kg !== null ? app(\App\Support\NumberFormat::class)->weight($record->first_weight_kg).' kg' : '—' }}</td>
                    <td class="ltr-value">{{ $record->second_weight_kg !== null ? app(\App\Support\NumberFormat::class)->weight($record->second_weight_kg).' kg' : '—' }}</td>
                    <td class="ltr-value"><strong>{{ app(\App\Support\NumberFormat::class)->weight($record->net_weight_kg) }} kg</strong></td>
                    <td>@if($record->dealDestination)<a href="{{ route('deals.show', $record->dealDestination->deal) }}">{{ $record->dealDestination->deal->reference_code }} · {{ $record->dealDestination->deal->product?->name }}</a>@else{{ $record->saleItem->sale->reference_code }} · {{ $record->saleItem->product->name }}@endif</td>
                    <td><span class="badge {{ $record->status === \App\Enums\WeighingStatus::Finalized ? 'badge-green' : 'badge-amber' }}"><span class="dot"></span>{{ $record->status->label() }}</span></td>
                    <td><div class="row-actions"><a href="{{ route('weighings.show', $record) }}">{{ __('common.view') }}</a>@if($canManage && !$record->deal_destination_id && $record->status === \App\Enums\WeighingStatus::Draft)<a href="{{ route('weighings.edit', $record) }}">{{ __('common.edit') }}</a>@endif</div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            {{ $weighings->links() }}
        @endif
    </section>
@endsection
