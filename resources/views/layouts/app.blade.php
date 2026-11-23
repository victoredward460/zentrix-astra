@extends('layouts.base')

@section('content')
    <div class="app-shell min-h-screen" data-app-shell>
        <button class="sidebar-backdrop" type="button" aria-label="Close navigation" data-sidebar-close></button>

        <aside class="app-sidebar" id="app-sidebar" aria-label="Primary navigation">
            <div class="sidebar-brand">
                <x-brand href="{{ route('home') }}" />
            </div>

            <div class="sidebar-context">
                <span class="context-mark"></span>
                DEFENSIVE SECURITY
            </div>

            <nav class="sidebar-navigation">
                <p class="nav-group-label">PLATFORM</p>
                <x-sidebar-item href="{{ route('dashboard') }}" :active="request()->routeIs('dashboard')" status="Overview">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="3" width="5.5" height="5.5" rx="1" /><rect x="11.5" y="3" width="5.5" height="5.5" rx="1" /><rect x="3" y="11.5" width="5.5" height="5.5" rx="1" /><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1" /></svg>
                    Dashboard
                </x-sidebar-item>
                <x-sidebar-item
                    :href="$platformFeatures['scanner']['enabled'] || auth()->user()->isAdministrator() ? route('scanner.assets.index') : null"
                    :active="request()->routeIs('scanner.*')"
                    :disabled="! $platformFeatures['scanner']['enabled'] && ! auth()->user()->isAdministrator()"
                    :status="$platformFeatures['scanner']['enabled'] ? 'Available' : (auth()->user()->isAdministrator() ? 'Admin access' : 'Temporarily paused')"
                >
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
                    Scanner
                </x-sidebar-item>
                <x-sidebar-item
                    :href="$platformFeatures['website-auditor']['enabled'] || auth()->user()->isAdministrator() ? route('website-auditor.index') : null"
                    :active="request()->routeIs('website-auditor.*')"
                    :disabled="! $platformFeatures['website-auditor']['enabled'] && ! auth()->user()->isAdministrator()"
                    :status="$platformFeatures['website-auditor']['enabled'] ? 'Available' : (auth()->user()->isAdministrator() ? 'Admin access' : 'Temporarily paused')"
                >
                    <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7" /><path d="M3.5 10h13M10 3c1.7 1.8 2.5 4.1 2.5 7s-.8 5.2-2.5 7c-1.7-1.8-2.5-4.1-2.5-7S8.3 4.8 10 3Z" /></svg>
                    Website Auditor
                </x-sidebar-item>
                <x-sidebar-item
                    :href="$platformFeatures['file-check']['enabled'] || auth()->user()->isAdministrator() ? route('file-check.index') : null"
                    :active="request()->routeIs('file-check.*')"
                    :disabled="! $platformFeatures['file-check']['enabled'] && ! auth()->user()->isAdministrator()"
                    :status="$platformFeatures['file-check']['enabled'] ? 'Available' : (auth()->user()->isAdministrator() ? 'Admin access' : 'Temporarily paused')"
                >
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 2.8h7l3.2 3.3v11H5z" /><path d="M12 2.8v3.5h3.2M7.5 10h5M7.5 13h5" /></svg>
                    File Check
                </x-sidebar-item>
                <x-sidebar-item
                    :href="$platformFeatures['ai-agent']['enabled'] || auth()->user()->isAdministrator() ? route('agent.index') : null"
                    :active="request()->routeIs('agent.*')"
                    :disabled="! $platformFeatures['ai-agent']['enabled'] && ! auth()->user()->isAdministrator()"
                    :status="$platformFeatures['ai-agent']['enabled'] ? (filled(config('services.openrouter.api_key')) && filled(config('services.openrouter.model')) ? 'Configured' : 'Setup required') : (auth()->user()->isAdministrator() ? 'Admin access' : 'Temporarily paused')"
                >
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 11.6 7l4.6-1.6-1.6 4.6 4.5 1.6-4.5 1.6 1.6 4.6-4.6-1.6L10 20l-1.6-4.5-4.6 1.6 1.6-4.6L1 10.9 5.5 9.3 3.9 4.7l4.6 1.6L10 2.5Z" transform="translate(0 -1.2) scale(.9)" /><circle cx="10" cy="9.5" r="2" /></svg>
                    AI Agent
                </x-sidebar-item>
                <x-sidebar-item
                    :href="$platformFeatures['academy']['enabled'] || auth()->user()->isAdministrator() ? route('academy.index') : null"
                    :active="request()->routeIs('academy.*')"
                    :disabled="! $platformFeatures['academy']['enabled'] && ! auth()->user()->isAdministrator()"
                    :status="$platformFeatures['academy']['enabled'] ? 'Available' : (auth()->user()->isAdministrator() ? 'Admin access' : 'Temporarily paused')"
                >
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4.5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M6.5 7h7M6.5 10h7M6.5 13h4" /></svg>
                    Academy
                </x-sidebar-item>

                <p class="nav-group-label nav-group-spaced">ADMINISTRATION</p>
                @if (auth()->user()->isAdministrator())
                    <x-sidebar-item href="{{ route('admin.dashboard') }}" :active="request()->routeIs('admin.dashboard')" status="Control centre" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><rect x="3" y="3" width="5.5" height="5.5" rx="1" /><rect x="11.5" y="3" width="5.5" height="5.5" rx="1" /><rect x="3" y="11.5" width="5.5" height="5.5" rx="1" /><rect x="11.5" y="11.5" width="5.5" height="5.5" rx="1" /></svg>
                        Admin overview
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.academy.index') }}" :active="request()->routeIs('admin.academy.*')" status="Content management" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4.5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M6.5 7h7M6.5 10h7M6.5 13h4" /></svg>
                        Academy content
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('safe-navigation.index') }}" :active="request()->routeIs('safe-navigation.*')" status="Admin only" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="M10 7v3.5m0 2.5h.01" /></svg>
                        Safe Navigation
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.users.index') }}" :active="request()->routeIs('admin.users.*')" status="Admin only" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="7" cy="7" r="3" /><path d="M2.5 16.5a4.5 4.5 0 0 1 9 0M13 5.2a3 3 0 0 1 0 5.6m1 2a4 4 0 0 1 3.5 3.7" /></svg>
                        User management
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.scans.index') }}" :active="request()->routeIs('admin.scans.*')" status="Owner-linked reports" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 16.5h14M5 13V8m5 5V4m5 9v-3" /></svg>
                        Scan activity
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.file-checks.index') }}" :active="request()->routeIs('admin.file-checks.*')" status="Metadata only" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M5 2.8h7l3.2 3.3v11H5z" /><path d="M12 2.8v3.5h3.2M7.5 10h5M7.5 13h5" /></svg>
                        File Check records
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.academy.progress') }}" :active="request()->routeIs('admin.academy.progress')" status="Learner records" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 4.5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><path d="M6.5 7h7M6.5 10h7M6.5 13h4" /></svg>
                        Academy progress
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.activity.index') }}" :active="request()->routeIs('admin.activity.*') || request()->routeIs('admin.security-events.*')" status="Audit trail" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 3.5h12v13H4zM7 7h6M7 10h6M7 13h3" /></svg>
                        Platform activity
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.configuration') }}" :active="request()->routeIs('admin.configuration')" status="Read-only status" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="3" /><path d="M10 2.5v2m0 11v2m7.5-7.5h-2m-11 0h-2m12.8-5.3-1.4 1.4m-7.8 7.8-1.4 1.4m12.1 0-1.4-1.4M6.2 6.2 4.8 4.8" /></svg>
                        Integration status
                    </x-sidebar-item>
                    <x-sidebar-item href="{{ route('admin.features.index') }}" :active="request()->routeIs('admin.features.*')" status="Feature controls" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M4 5h12M4 10h12M4 15h12" /><circle cx="8" cy="5" r="2" /><circle cx="13" cy="10" r="2" /><circle cx="7" cy="15" r="2" /></svg>
                        Platform controls
                    </x-sidebar-item>
                @else
                    <x-sidebar-item disabled status="Admin only" admin>
                        <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="M10 7v3.5m0 2.5h.01" /></svg>
                        Safe Navigation
                    </x-sidebar-item>
                @endif
            </nav>

            <div class="sidebar-bottom">
                <div class="sidebar-safety">
                    <span class="safety-icon" aria-hidden="true">
                        <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
                    </span>
                    <span><strong>Defense first</strong><small>Authorized use only</small></span>
                </div>
                <a class="sidebar-back-link" href="{{ route('home') }}">
                    <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M12.5 4 6.5 10l6 6" /></svg>
                    Back to overview
                </a>
                <a class="sidebar-back-link" href="{{ route('profile.edit') }}">Account profile</a>
            </div>
        </aside>

        <div class="app-main flex min-h-screen flex-col">
            <header class="app-topbar">
                <div class="topbar-leading">
                    <button class="menu-toggle sidebar-toggle" type="button" aria-expanded="false"
                        aria-controls="app-sidebar" data-sidebar-toggle>
                        <span class="sr-only">Open application navigation</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" /></svg>
                    </button>
                    <div class="topbar-brand"><x-brand href="{{ route('home') }}" /></div>
                    <span class="topbar-divider"></span>
                    <span class="topbar-section">@yield('title', 'Security dashboard')</span>
                </div>
                <div class="topbar-trailing">
                    <button class="button button-small button-outline pwa-install-button" type="button" data-pwa-install>Install App</button>
                    <span class="workspace-indicator"><span></span> SIGNED-IN WORKSPACE</span>
                    <a class="topbar-account" href="{{ route('profile.edit') }}">
                        <span class="topbar-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                        <span class="topbar-account-name">{{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="topbar-signout" type="submit">Sign out</button>
                    </form>
                </div>
            </header>

            <main class="workspace-main flex-1">
                @yield('workspace')
            </main>
        </div>
    </div>

    <x-modal id="module-info-modal" title="Planned modules are not available yet">
        <p>Modules that are still in development are clearly marked and will not pretend to be operational.</p>
        <p>Safe Navigation will remain restricted to authorized administrators.</p>
        <x-slot:actions>
            <button class="button button-primary button-small" type="button" data-modal-close>Understood</button>
        </x-slot:actions>
    </x-modal>
@endsection
