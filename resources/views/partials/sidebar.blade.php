@php
    $user = auth()->user();
    $canAccounts = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');
    $canStorage = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER', 'WAREHOUSE_OFFICER');
    $canConfigure = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN');
@endphp
<aside class="sidebar" data-sidebar>
    <div class="brand">
        <img class="brand-mark" src="{{ asset('images/naftyar-mark.png') }}" alt="" width="42" height="50">
        <div><div class="brand-title">{{ __('common.app_name') }}</div><div class="brand-sub">{{ __('common.app_subtitle') }}</div></div>
    </div>

    <nav class="primary-office-nav" aria-label="{{ __('navigation.main') }}">
        <a class="nav-linkx {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-grid"></i><span>{{ __('navigation.dashboard') }}</span></a>
        <a class="nav-linkx {{ request()->routeIs('office.transactions', 'deals.*', 'purchases.*', 'sales.*', 'weighings.*') ? 'active' : '' }}" href="{{ route('office.transactions') }}"><i class="bi bi-arrow-left-right"></i><span>{{ __('navigation.trade') }}</span></a>
        @if($canAccounts)<a class="nav-linkx {{ request()->routeIs('office.accounts', 'partners.*', 'finance.receivables', 'finance.payables', 'payments.*') ? 'active' : '' }}" href="{{ route('office.accounts') }}"><i class="bi bi-journal-text"></i><span>{{ __('navigation.accounts') }}</span></a>@endif
        @if($user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT'))<a class="nav-linkx {{ request()->routeIs('finance.journal', 'money-movements.*', 'expenses.*', 'payments.*') ? 'active' : '' }}" href="{{ route('finance.journal') }}"><i class="bi bi-calendar2-week"></i><span>{{ __('navigation.journal') }}</span></a>@endif
        @if($canStorage)<a class="nav-linkx {{ request()->routeIs('office.storage', 'inventory.*') ? 'active' : '' }}" href="{{ route('office.storage') }}"><i class="bi bi-boxes"></i><span>{{ __('navigation.storage') }}</span></a>@endif
        <a class="nav-linkx {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart"></i><span>{{ __('navigation.reports') }}</span></a>
    </nav>

    @if($canConfigure)
        <div class="nav-section office-admin-link"><a class="nav-linkx {{ request()->routeIs('office.configuration', 'products.*', 'warehouses.*', 'contracts.*', 'financial-accounts.*', 'capital-*') ? 'active' : '' }}" href="{{ route('office.configuration') }}"><i class="bi bi-gear"></i><span>{{ __('navigation.settings') }}</span></a></div>
    @endif
</aside>
