@php
    $code = $exception->getStatusCode();
    $copy = __('errors');
    $font = rescue(fn () => Vite::asset('resources/fonts/Vazirmatn.woff2'), null, report: false);
    [$heading, $message] = $copy[$code] ?? $copy[$code >= 500 ? 500 : 'request'];
@endphp
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }} | {{ __('common.app_name') }}</title>
    @include('partials.favicon')
    {{-- Error recovery also works if the frontend build or the database is unavailable. --}}
    <style>
        @if($font)
        @font-face { font-family: Vazirmatn; src: url("{{ $font }}") format("woff2"); font-weight: 100 900; font-display: swap; }
        @endif
        body { margin: 0; background: #f5f7f8; color: #17242b; font: 1rem/1.85 Vazirmatn, Tahoma, sans-serif; }
        main { margin: 12vh auto; max-width: 32rem; padding: 1.75rem; background: white; border-block-start: .3rem solid #0b5f59; }
        h1 { font-size: 1.5rem; } a { color: #0b5f59; } small { color: #607079; }
    </style>
</head>
<body>
    <main>
        <small>{{ __('common.app_name') }} · <bdi>{{ $code }}</bdi></small>
        <h1>{{ $heading }}</h1>
        <p>{{ $message }}</p>
        <a href="{{ url('/') }}">بازگشت به نفت‌یار</a>
    </main>
</body>
</html>
