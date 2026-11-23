@extends('layouts.app')

@section('title', __('partners.title'))

@section('content')
    @php($canManage = auth()->user()->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'PURCHASE_SALES_OFFICER'))
    <x-page-header :title="__('partners.title')" :subtitle="__('partners.subtitle')">
        @if($canManage)<a class="btnx btn-primaryx" href="{{ route('partners.create') }}"><i class="bi bi-plus-lg"></i>{{ __('partners.add') }}</a>@endif
    </x-page-header>
    <section class="cardx card-pad">
        <form class="filters" method="GET" action="{{ route('partners.index') }}">
            <input class="control grow" name="search" value="{{ request('search') }}" placeholder="{{ __('common.search') }}...">
            <select class="control" name="role"><option value="">{{ __('partners.all_roles') }}</option>@foreach($partnerRoles as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>@endforeach</select>
            <select class="control" name="status"><option value="">{{ __('common.all_statuses') }}</option><option value="active" @selected(request('status') === 'active')>{{ __('common.active') }}</option><option value="inactive" @selected(request('status') === 'inactive')>{{ __('common.inactive') }}</option></select>
            <button class="btnx btn-lightx" type="submit"><i class="bi bi-funnel"></i>{{ __('common.filter') }}</button>
        </form>
        @if($partners->isEmpty())
            <div class="empty"><i class="bi bi-people"></i><div class="empty-title">{{ __('common.no_records') }}</div></div>
        @else
            <div class="table-wrap"><table class="tablex"><thead><tr><th>{{ __('partners.name') }}</th><th>{{ __('partners.relationship') }}</th><th>{{ __('common.phone') }}</th><th>{{ __('common.address') }}</th><th>{{ __('common.status') }}</th><th>{{ __('common.actions') }}</th></tr></thead><tbody>
            @foreach($partners as $partner)
                <tr>
                    <td><div class="td-main">{{ $partner->name }}</div><div class="td-sub">{{ $partner->reference_code }}</div></td>
                    <td><div class="role-badges">@foreach($partner->roles as $assignment)<span class="badge {{ match($assignment->role->value) { 'CUSTOMER' => 'badge-blue', 'SUPPLIER' => 'badge-gray', 'SARAF' => 'badge-amber', default => 'badge-green' } }}">{{ $assignment->role->label() }}</span>@endforeach</div></td>
                    <td class="ltr-value">{{ $partner->phone ?: __('common.not_available') }}</td><td>{{ $partner->address ?: __('common.not_available') }}</td><td><x-status-badge :active="$partner->is_active" /></td>
                    <td><div class="row-actions"><a href="{{ route('partners.show', $partner) }}">{{ __('common.view') }}</a>@if($canManage)<a href="{{ route('partners.edit', $partner) }}">{{ __('common.edit') }}</a><form method="POST" action="{{ route('partners.destroy', $partner) }}" data-confirm="{{ __('common.confirm_delete') }}">@csrf @method('DELETE')<button class="link-danger" type="submit">{{ __('common.delete') }}</button></form>@endif</div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            {{ $partners->links() }}
        @endif
    </section>
@endsection
