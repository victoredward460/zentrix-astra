@extends('layouts.base')

@section('title', 'Defensive security, clearly designed')
@section('description', 'A unified, evidence-led cybersecurity workspace for authorized defensive security work.')

@section('content')
    <div class="marketing-shell min-h-screen">
        <header class="marketing-header">
            <div class="container header-inner mx-auto flex items-center justify-between">
                <x-brand />

                <button class="button button-small button-outline pwa-install-button marketing-install-button" type="button" data-pwa-install>Install App</button>

                <button class="menu-toggle marketing-menu-toggle" type="button" aria-expanded="false"
                    aria-controls="marketing-navigation" data-menu-toggle="marketing-navigation">
                    <span class="sr-only">Toggle navigation</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>

                <nav class="marketing-navigation flex items-center" id="marketing-navigation" aria-label="Main navigation">
                    <a href="#platform">Platform</a>
                    <a href="#principles">Principles</a>
                    <a href="#modules">Modules</a>
                    <button class="button button-small button-outline pwa-install-button" type="button" data-pwa-install>Install App</button>
                    @guest
                        <a href="{{ route('login') }}">Sign in</a>
                        @if ($platformFeatures['public-registration']['enabled'])
                            <a class="button button-small button-outline" href="{{ route('register') }}">Create account</a>
                        @endif
                    @else
                        <a class="button button-small button-outline" href="{{ route('dashboard') }}">
                        Open workspace
                        <svg viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M4 10h11m-4-4 4 4-4 4" />
                        </svg>
                    </a>
                    @endguest
                </nav>
            </div>
        </header>

        <main>
            <section class="hero container grid items-center" id="platform">
                <div class="hero-copy">
                    <div class="eyebrow">
                        <span class="status-dot" aria-hidden="true"></span>
                        DEFENSIVE SECURITY WORKSPACE
                    </div>
                    <h1>Security work<br><span>with signal.</span></h1>
                    <p class="hero-lede">
                        A considered workspace for authorized security checks, clear evidence, and confident next steps.
                    </p>
                    <div class="hero-actions">
                        @guest
                            @if ($platformFeatures['public-registration']['enabled'])
                                <a class="button button-primary" href="{{ route('register') }}">
                                    Create your account
                                    <svg viewBox="0 0 20 20" aria-hidden="true">
                                        <path d="M4 10h11m-4-4 4 4-4 4" />
                                    </svg>
                                </a>
                            @endif
                            <a class="button button-quiet" href="{{ route('login') }}">Sign in</a>
                        @else
                            <a class="button button-primary" href="{{ route('dashboard') }}">
                            Open workspace
                            <svg viewBox="0 0 20 20" aria-hidden="true">
                                <path d="M4 10h11m-4-4 4 4-4 4" />
                            </svg>
                        </a>
                        @endguest
                        <a class="button button-quiet" href="#modules">Discover the platform</a>
                    </div>
                    <div class="hero-note">
                        <span class="note-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20"><path d="M10 2.5 16 5v4.7c0 3.5-2.5 6.1-6 7.8-3.5-1.7-6-4.3-6-7.8V5l6-2.5Z" /><path d="m7.5 9.8 1.7 1.7 3.5-3.7" /></svg>
                        </span>
                        <span><strong>Platform preview</strong><span>Passive scanning requires verified ownership and authorization.</span></span>
                    </div>
                </div>

                <div class="hero-visual" aria-hidden="true">
                    <div class="visual-orbit orbit-one"></div>
                    <div class="visual-orbit orbit-two"></div>
                    <div class="visual-glow"></div>
                    <div class="preview-window">
                        <div class="preview-window-bar">
                            <div class="window-dots"><i></i><i></i><i></i></div>
                            <span>ASTRA / WORKSPACE</span>
                            <span class="window-lock">
                                <svg viewBox="0 0 16 16"><path d="M4.5 7V5.2a3.5 3.5 0 0 1 7 0V7M3.5 7h9v6h-9z" /></svg>
                            </span>
                        </div>
                        <div class="preview-window-content">
                            <div class="preview-label">YOUR SECURITY WORKSPACE</div>
                            <div class="preview-heading">
                                <div><span class="preview-heading-line"></span><span class="preview-heading-line short"></span></div>
                                <span class="preview-chip">PREVIEW</span>
                            </div>
                            <div class="preview-focus">
                                <div class="focus-mark">
                                    <svg viewBox="0 0 40 40"><path d="M20 3.5 34 9v10.8c0 8.2-5.8 14.1-14 18.2C11.8 33.9 6 28 6 19.8V9l14-5.5Z" /><path d="m14 20 4 4 8-8" /></svg>
                                </div>
                                <div><span class="preview-heading-line medium"></span><span class="preview-heading-line small"></span></div>
                            </div>
                            <div class="preview-module-list">
                                <div><span class="module-glyph glyph-blue">S</span><span>Security Scanner</span><span class="mini-status">{{ $platformFeatures['scanner']['enabled'] ? 'AVAILABLE' : 'PAUSED' }}</span></div>
                                <div><span class="module-glyph glyph-cyan">W</span><span>Website Auditor</span><span class="mini-status">{{ $platformFeatures['website-auditor']['enabled'] ? 'AVAILABLE' : 'PAUSED' }}</span></div>
                                <div><span class="module-glyph glyph-violet">F</span><span>File Check</span><span class="mini-status">{{ $platformFeatures['file-check']['enabled'] ? 'AVAILABLE' : 'PAUSED' }}</span></div>
                            </div>
                            <div class="preview-footer"><span></span>Built for deliberate defense</div>
                        </div>
                    </div>
                    <div class="visual-caption"><span></span> A unified view. Grounded in real evidence.</div>
                </div>
            </section>

            <section class="principles-section" id="principles">
                <div class="container">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">BUILT WITH INTENTION</p>
                            <h2>Clarity is a security feature.</h2>
                        </div>
                        <p>Every part of Astra is designed to make defensive work understandable, controlled, and accountable.</p>
                    </div>

                    <div class="principle-grid grid">
                        <x-panel class="principle-card">
                            <span class="principle-icon icon-cyan" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 4.2-3.2 7.3-8 9-4.8-1.7-8-4.8-8-9V6l8-3Z" /><path d="m8.5 12 2.2 2.2 4.8-5" /></svg>
                            </span>
                            <h3>Authorized by design</h3>
                            <p>Assessment workflows are planned around explicit ownership, scope, and safe boundaries.</p>
                        </x-panel>
                        <x-panel class="principle-card">
                            <span class="principle-icon icon-blue" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M4 5h16v11H4zM8 20h8M12 16v4" /><path d="M7.5 9h3M7.5 12h6" /></svg>
                            </span>
                            <h3>Evidence over noise</h3>
                            <p>Security conclusions should be traceable to observations, with uncertainty made visible.</p>
                        </x-panel>
                        <x-panel class="principle-card">
                            <span class="principle-icon icon-violet" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="M12 3v2m0 14v2M3 12h2m14 0h2M5.6 5.6 7 7m10 10 1.4 1.4m0-12.8L17 7M7 17l-1.4 1.4" /><circle cx="12" cy="12" r="5" /><path d="m10 12 1.3 1.3L14.5 10" /></svg>
                            </span>
                            <h3>Human-led decisions</h3>
                            <p>Automation can help explain results, but it must never invent findings or replace judgment.</p>
                        </x-panel>
                    </div>
                </div>
            </section>

            <section class="modules-section" id="modules">
                <div class="container">
                    <div class="section-heading">
                        <div>
                            <p class="eyebrow">ONE CONNECTED PLATFORM</p>
                            <h2>Designed to work together.</h2>
                        </div>
                        <p>Core modules are available to authorized accounts when enabled by an administrator. Safe Navigation remains administrator-only, and reputation results depend on provider availability.</p>
                    </div>

                    @php
                        $modules = [
                            ['name' => 'Security Scanner', 'description' => 'Limited passive checks for DNS-verified hostnames you are authorized to assess.', 'mark' => '01', 'tone' => 'blue', 'feature' => 'scanner'],
                            ['name' => 'Website Auditor', 'description' => 'Bounded, passive checks on DNS-verified authorized websites.', 'mark' => '02', 'tone' => 'cyan', 'feature' => 'website-auditor'],
                            ['name' => 'File Check', 'description' => 'Private metadata and SHA-256 records; no execution or malware verdict.', 'mark' => '03', 'tone' => 'violet', 'feature' => 'file-check'],
                            ['name' => 'Central AI Agent', 'description' => 'Account-scoped history and evidence-grounded explanations; providers may use sent prompts for training.', 'mark' => '04', 'tone' => 'blue', 'feature' => 'ai-agent', 'integration_status' => 'FREE ROUTE · AVAILABILITY VARIES'],
                            ['name' => 'Cybersecurity Academy', 'description' => 'Original beginner-friendly lessons, knowledge checks, and saved learning progress.', 'mark' => '05', 'tone' => 'cyan', 'feature' => 'academy'],
                            ['name' => 'Security Dashboard', 'description' => 'A clear view of real, account-scoped platform activity.', 'mark' => '06', 'tone' => 'violet', 'available' => true],
                            ['name' => 'Safe Navigation', 'description' => 'Admin-only URL reputation workflow; results may be unavailable or inconclusive.', 'mark' => '07', 'tone' => 'amber', 'admin' => true, 'available' => true, 'integration_status' => 'PROVIDER-DEPENDENT'],
                            ['name' => 'Platform Administration', 'description' => 'Admin-only account status, role management, module reports, and minimized audit events.', 'mark' => '08', 'tone' => 'blue', 'admin' => true, 'available' => true],
                        ];
                        foreach ($modules as &$module) {
                            if (isset($module['feature'])) {
                                $module['available'] = $platformFeatures[$module['feature']]['enabled'];
                            }
                        }
                        unset($module);
                    @endphp

                    <div class="module-grid grid">
                        @foreach ($modules as $module)
                            <x-panel class="module-card">
                                <div class="module-card-top">
                                    <span class="module-number tone-{{ $module['tone'] }}">{{ $module['mark'] }}</span>
                                    @php($badgeTone = ($module['admin'] ?? false) ? 'warning' : (($module['available'] ?? false) ? 'accent' : 'muted'))
                                    <x-status-badge :tone="$badgeTone">
                                        @if ($module['admin'] ?? false)
                                            ADMIN ONLY / {{ $module['integration_status'] ?? (($module['available'] ?? false) ? 'AVAILABLE' : 'PLANNED') }}
                                        @elseif (isset($module['feature']) && ! $module['available']) TEMPORARILY PAUSED
                                        @elseif (isset($module['integration_status'])) {{ $module['integration_status'] }}
                                        @elseif ($module['available'] ?? false) AVAILABLE
                                        @else PLANNED
                                        @endif
                                    </x-status-badge>
                                </div>
                                <h3>{{ $module['name'] }}</h3>
                                <p>{{ $module['description'] }}</p>
                            </x-panel>
                        @endforeach
                    </div>

                    <div class="modules-note">
                        <span class="note-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20"><circle cx="10" cy="10" r="7.5" /><path d="M10 9v4m0-6h.01" /></svg>
                        </span>
                        <p><strong>Assessment limits apply.</strong> Website checks require DNS-verified, explicitly authorized hostnames. File Check records private metadata and hashes only; it does not detect malware. Academy learning progress is private to each account. Safe Navigation is admin-only and depends on reputation-provider availability. AI uses free OpenRouter routing, may be rate-limited, and its provider may use submitted prompts for training. Administrators may temporarily pause user-facing modules.</p>
                    </div>
                </div>
            </section>
        </main>

        <footer class="marketing-footer">
            <div class="container footer-inner">
                <x-brand />
                <p>Defensive security, made deliberate.</p>
                <span>Copyright {{ date('Y') }} ZENTRIX ASTRA</span>
            </div>
        </footer>
    </div>
@endsection
