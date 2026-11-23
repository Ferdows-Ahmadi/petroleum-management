<header class="topbar">
    <div class="topbar-start">
        <button class="icon-btn mobile-menu" type="button" data-menu aria-label="{{ __('navigation.main') }}">
            <i class="bi bi-list"></i>
        </button>
    </div>
    <div class="top-actions">
        <span class="badge badge-gray ltr-value"><i class="bi bi-calendar3"></i> @jalali(now())</span>
        <div class="profile">
            <div class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
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
