@php
    $user = auth()->user();
    $canAccounts = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');
    $canStorage = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER', 'WAREHOUSE_OFFICER');
    $canConfigure = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN');
@endphp
<nav class="primary-office-nav" id="office-menu" aria-label="{{ __('navigation.main') }}">
    <a class="nav-linkx" href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>{{ __('navigation.dashboard') }}</a>
    <a class="nav-linkx" href="{{ route('office.transactions') }}" @if(request()->routeIs('office.transactions', 'deals.*', 'purchases.*', 'sales.*', 'weighings.*')) aria-current="page" @endif>{{ __('navigation.trade') }}</a>
    @if($canAccounts)
        <a class="nav-linkx" href="{{ route('office.accounts') }}" @if(request()->routeIs('office.accounts', 'partners.*', 'finance.receivables', 'finance.payables')) aria-current="page" @endif>{{ __('navigation.accounts') }}</a>
    @endif
    @if($user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT'))
        <a class="nav-linkx" href="{{ route('finance.journal') }}" @if(request()->routeIs('finance.journal', 'office.finance', 'money-movements.*', 'expenses.*', 'payments.*')) aria-current="page" @endif>{{ __('navigation.journal') }}</a>
    @endif
    @if($canStorage)
        <a class="nav-linkx" href="{{ route('office.storage') }}" @if(request()->routeIs('office.storage', 'inventory.*')) aria-current="page" @endif>{{ __('navigation.storage') }}</a>
    @endif
    <a class="nav-linkx" href="{{ route(\App\Support\ReportNavigation::defaultRoute($user)) }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif>{{ __('navigation.reports') }}</a>
    @if($canConfigure)
        <a class="nav-linkx" href="{{ route('office.configuration') }}" @if(request()->routeIs('office.configuration', 'users.*', 'products.*', 'warehouses.*', 'contracts.*', 'financial-accounts.*', 'capital-*')) aria-current="page" @endif>{{ __('navigation.settings') }}</a>
    @endif
</nav>
