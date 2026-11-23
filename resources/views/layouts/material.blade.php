<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#07111d">
    <title>@yield('title', 'Shared Academy material') | ZENTRIX ASTRA</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/pwa.js'])
</head>
<body class="min-h-screen bg-ink-950 font-sans text-slate-100 antialiased">
    <div class="page-intro" aria-hidden="true">
        <img src="{{ asset('brand/zentrix-astra-mark.svg') }}" alt="">
        <span><strong>ZENTRIX ASTRA</strong><small>DEFENSIVE SECURITY</small></span>
    </div>
    @yield('content')
</body>
</html>
