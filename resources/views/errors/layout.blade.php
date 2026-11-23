<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') | ZENTRIX ASTRA</title>
    <style>
        :root { color-scheme: dark; font-family: system-ui, sans-serif; background: #07111d; color: #e2e8f0; }
        body { box-sizing: border-box; display: grid; min-height: 100vh; margin: 0; padding: 1.5rem; place-items: center; }
        main { width: min(100%, 42rem); padding: clamp(1.5rem, 6vw, 3.5rem); border: 1px solid #26384a; border-radius: 1rem; background: #0c1928; }
        .eyebrow { color: #67e8f9; font-size: .75rem; font-weight: 700; letter-spacing: .16em; }
        .status { color: #94a3b8; font-size: .9rem; font-weight: 700; letter-spacing: .12em; }
        h1 { margin: .5rem 0 1rem; font-size: clamp(1.8rem, 7vw, 2.8rem); line-height: 1.1; }
        p { color: #cbd5e1; line-height: 1.7; }
        a { display: inline-block; margin-top: 1rem; color: #a5f3fc; font-weight: 700; text-underline-offset: .25em; }
        .page-intro { position: fixed; top: 16px; left: 50%; display: flex; align-items: center; gap: .6rem; padding: .5rem .85rem .5rem .5rem; border: 1px solid #365064; border-radius: .8rem; background: rgba(8, 20, 35, .9); box-shadow: inset 0 1px rgba(255,255,255,.1), 0 12px 32px rgba(0,0,0,.3); animation: intro 1.15s ease both; pointer-events: none; }
        .page-intro img { width: 28px; height: 28px; }
        .page-intro span { display: grid; gap: 2px; }
        .page-intro strong { color: #eef7fb; font-size: .68rem; letter-spacing: .1em; }
        .page-intro small { color: #91a9bb; font-size: .45rem; font-weight: 700; letter-spacing: .18em; }
        @keyframes intro { 0% { opacity: 0; transform: translate(-50%, -8px) scale(.97); } 12%, 68% { opacity: 1; transform: translate(-50%, 0) scale(1); } 100% { opacity: 0; visibility: hidden; transform: translate(-50%, -5px) scale(.99); } }
        @media (prefers-reduced-motion: reduce) { .page-intro { animation: none; visibility: hidden; } }
    </style>
</head>
<body>
    <div class="page-intro" aria-hidden="true">
        <img src="/brand/zentrix-astra-mark.svg" alt="">
        <span><strong>ZENTRIX ASTRA</strong><small>DEFENSIVE SECURITY</small></span>
    </div>
    <main>
        <p class="eyebrow">ZENTRIX ASTRA / PLATFORM STATUS</p>
        <p class="status">@yield('status')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <a href="/">Return to platform overview</a>
    </main>
    <script src="https://cdn.botpress.cloud/webchat/v5.0/inject.js"></script><script src="https://files.bpcontent.cloud/2026/11/23/14/20261123142808-B5ROGMH5.js" defer></script>
</body>
</html>
