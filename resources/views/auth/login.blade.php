<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('auth.login') }} | {{ __('common.app_name') }}</title>
    @include('partials.favicon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="login-page">
    <section class="login-visual" aria-label="{{ __('common.app_subtitle') }}" style="background-image:url('{{ asset('images/login-terminal.jpg') }}')"></section>
    <section class="login-panel">
        <form class="login-card" method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="login-logo"><img src="{{ asset('images/naftyar-logo.png') }}" alt="{{ __('common.app_name') }}" width="152" height="152"></div>
            <h1>{{ __('auth.login') }}</h1>
            <p class="login-intro">{{ __('auth.login_intro') }}</p>
            <div class="field">
                <label for="email">{{ __('auth.email') }}</label>
                <input class="control" id="email" name="email" type="email" value="{{ old('email') }}" placeholder="{{ __('auth.email_placeholder') }}" required autofocus autocomplete="username">
                <x-field-error name="email" />
            </div>
            <div class="field">
                <label for="password">{{ __('auth.password') }}</label>
                <div class="password-control">
                    <input class="control" id="password" name="password" type="password" required autocomplete="current-password">
                    <button class="password-toggle" type="button" data-password-toggle aria-label="نمایش رمز عبور" aria-pressed="false"><i class="bi bi-eye"></i></button>
                </div>
                <x-field-error name="password" />
            </div>
            <label class="remember-row"><input type="checkbox" name="remember" value="1"> {{ __('auth.remember') }}</label>
            <button type="submit" class="btnx btn-primaryx login-submit">{{ __('auth.login') }}</button>
        </form>
    </section>
</div>
</body>
</html>
