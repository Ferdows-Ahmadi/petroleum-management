@if(auth()->user()->hasAnyRole('GENERAL_MANAGER', 'ACCOUNTANT', 'SYSTEM_ADMIN'))
<nav class="report-switch no-print" aria-label="نوع گزارش حساب">
    <a class="workspace-tab {{ request()->routeIs('reports.accounts') ? 'active' : '' }}" href="{{ route('reports.accounts') }}">حساب اشخاص</a>
    <a class="workspace-tab {{ request()->routeIs('reports.accounts.journal') ? 'active' : '' }}" href="{{ route('reports.accounts.journal') }}">روزنامچهٔ حساب و صراف</a>
</nav>
@endif
