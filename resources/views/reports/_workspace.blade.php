@php
    $scope = collect([
        ($products ?? $report['products'] ?? collect())->firstWhere('id', request('product_id'))?->name,
        ($warehouses ?? $report['warehouses'] ?? collect())->firstWhere('id', request('warehouse_id'))?->name,
        isset($partners) && !isset($selected) ? $partners->find(request('partner_id') ?? request('capital_partner_id'))?->name : null,
        isset($accounts) ? $accounts->find(request('financial_account_id'))?->name : null,
    ])->filter()->implode(' · ');
@endphp
<div class="section-head no-print">
    <nav class="workspace-tabs office-wide-tabs" aria-label="گزارش‌ها">
        @foreach(\App\Support\ReportNavigation::tabsFor(auth()->user()) as $route => $label)
            @php($active = request()->routeIs($route, $route.'.*'))
            <a class="workspace-tab {{ $active ? 'active' : '' }}" @if($active) aria-current="page" @endif href="{{ route($route) }}">{{ $label }}</a>
        @endforeach
    </nav>
    <button class="btnx btn-lightx report-print" type="button" onclick="window.print()"><i class="bi bi-printer" aria-hidden="true"></i>چاپ / PDF</button>
</div>
<div class="print-header"><img src="{{ asset('images/naftyar-logo.png') }}" alt="{{ __('common.app_name') }}"><strong>{{ trim($__env->yieldContent('title')) }}</strong></div>
<div class="report-period">
    @if(isset($date))
        <span>تاریخ <bdi>@jalali($date)</bdi></span>
    @elseif(!empty($dateInputs['date_from']) || !empty($dateInputs['date_to']))
        @if(!empty($dateInputs['date_from']))<span>از <bdi>{{ $dateInputs['date_from'] }}</bdi></span>@endif
        @if(!empty($dateInputs['date_to']))<span>تا <bdi>{{ $dateInputs['date_to'] }}</bdi></span>@endif
    @else
        <span>دوره: تمام اسناد ثبت‌شده</span>
    @endif
    @if($reportUnit ?? true)
        <span>واحد: <bdi>{{ $reportUnit ?? 'USD' }}</bdi></span>
    @endif
    @if(!empty($reportSubject) || $scope)<strong>{{ $reportSubject ?? $scope }}</strong>@endif
</div>
