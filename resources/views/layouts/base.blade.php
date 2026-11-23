<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="no-js">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'ZENTRIX ASTRA defensive security platform.')">
    <meta name="theme-color" content="#07111d">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ZENTRIX">
    <title>@yield('title', 'ZENTRIX ASTRA') | ZENTRIX ASTRA</title>
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/pwa.js'])
    @stack('head')
</head>
<body class="min-h-screen bg-ink-950 font-sans text-slate-100 antialiased">
    <div class="page-intro" aria-hidden="true">
        <img src="{{ asset('brand/zentrix-astra-mark.svg') }}" alt="">
        <span><strong>ZENTRIX ASTRA</strong><small>DEFENSIVE SECURITY</small></span>
    </div>
    @yield('content')
    <x-toast-region />
    <x-modal id="pwa-install-help" title="Install ZENTRIX ASTRA">
        <p data-pwa-instructions>Install ZENTRIX ASTRA on this device for quick access.</p>
        <p data-pwa-ios hidden>On your iPhone or iPad, open this page in Safari, tap the Share button, choose <strong>Add to Home Screen</strong>, then tap Add.</p>
        <p data-pwa-android hidden>In Chrome, use the install prompt or open the browser menu and choose <strong>Install app</strong> or <strong>Add to Home screen</strong>.</p>
        <p data-pwa-desktop hidden>Use the install icon in your browser's address bar, or open the browser menu and choose <strong>Install ZENTRIX ASTRA</strong>.</p>
        <p>Installation is available only from a secure HTTPS website (or localhost during development).</p>
        <x-slot:actions>
            <button class="button button-primary button-small" type="button" data-modal-close>Done</button>
        </x-slot:actions>
    </x-modal>
    <script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script><script src="https://files.bpcontent.cloud/2026/11/23/14/20261123142808-B5ROGMH5.js" defer></script>
</body>
</html>
