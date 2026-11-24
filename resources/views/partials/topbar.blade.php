<header class="topbar">
    <a class="brand" href="{{ route('dashboard') }}" aria-label="{{ __('common.app_name') }}">
        <img class="brand-mark" src="{{ asset('images/naftyar-mark.png') }}" alt="" width="40" height="46">
        <span><span class="brand-title">{{ __('common.app_name') }}</span><span class="brand-sub">{{ __('common.app_subtitle') }}</span></span>
    </a>
    <button class="icon-btn mobile-menu" type="button" data-menu aria-expanded="false" aria-controls="office-menu" aria-label="فهرست بخش‌ها"><i class="bi bi-list" aria-hidden="true"></i></button>
    @include('partials.navigation')
    <div class="top-actions">
        <span class="badge badge-gray ltr-value"><i class="bi bi-calendar3"></i> @jalali(now())</span>
        <div class="profile">
            <div class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
            <div class="profile-meta">
                <div class="profile-name">{{ auth()->user()->name }}</div>
                <div class="profile-role">{{ auth()->user()->role->label() }}</div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="icon-btn" type="submit" title="{{ __('auth.logout') }}" aria-label="{{ __('auth.logout') }}">
                <i class="bi bi-box-arrow-left"></i>
            </button>
        </form>
    </div>
</header>
