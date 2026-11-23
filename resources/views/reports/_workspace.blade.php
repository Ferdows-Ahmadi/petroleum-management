@php
    $user = auth()->user();
    $tradeReports = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER', 'WAREHOUSE_OFFICER');
    $accountReports = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT', 'PURCHASE_SALES_OFFICER');
    $financeReports = $user->hasAnyRole('GENERAL_MANAGER', 'SYSTEM_ADMIN', 'ACCOUNTANT');
@endphp
<div class="print-header"><img src="{{ asset('images/naftyar-logo.png') }}" alt="{{ __('common.app_name') }}"><div><strong>{{ $reportTitle ?? trim($__env->yieldContent('title')) }}</strong><div class="ltr-value">@jalali(now())</div></div></div>
<div class="section-head no-print">
    <nav class="workspace-tabs office-wide-tabs" aria-label="گزارش‌ها">
        @if($tradeReports)<a class="workspace-tab {{ request()->routeIs('reports.purchases') ? 'active' : '' }}" href="{{ route('reports.purchases') }}">خرید</a><a class="workspace-tab {{ request()->routeIs('reports.sales') ? 'active' : '' }}" href="{{ route('reports.sales') }}">فروش</a>@endif
        <a class="workspace-tab {{ request()->routeIs('reports.weighings') ? 'active' : '' }}" href="{{ route('reports.weighings') }}">وزن‌کشی</a>
        @if($accountReports)<a class="workspace-tab {{ request()->routeIs('reports.settlements') ? 'active' : '' }}" href="{{ route('reports.settlements') }}">تصفیه</a><a class="workspace-tab" href="{{ route('office.accounts') }}">حساب‌ها</a>@endif
        @if($tradeReports)<a class="workspace-tab" href="{{ route('office.storage') }}">ذخیره</a>@endif
        @if($financeReports)<a class="workspace-tab {{ request()->routeIs('reports.expenses') ? 'active' : '' }}" href="{{ route('reports.expenses') }}">مصارف</a><a class="workspace-tab {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}" href="{{ route('reports.profit-loss') }}">نتیجه مالی</a><a class="workspace-tab {{ request()->routeIs('reports.capital') ? 'active' : '' }}" href="{{ route('reports.capital') }}">سرمایه</a>@endif
    </nav>
    @unless(request()->routeIs('reports.index'))<button class="btnx btn-lightx" type="button" onclick="window.print()"><i class="bi bi-printer"></i>چاپ / PDF</button>@endunless
</div>
