@use('App\Support\Settings')
@use('Filament\Facades\Filament')
{{-- The member pages' frame, built like the admin panel's so both look and work the same: a topbar with
     the brand, a sidebar menu on wide screens that becomes a slide-in drawer behind the menu button on
     phones (CSS only), the panel's Inter font and its light or dark theme. --}}
@php
    $user = auth()->user();
    $menu = array_filter([
        ['Greens', route('home'), 'heroicon-o-square-3-stack-3d', request()->routeIs('home', 'greens.*', 'bookings.create', 'bookings.confirmation')],
        $user ? ['My bookings', route('bookings.index'), 'heroicon-o-calendar-days', request()->routeIs('bookings.index')] : null,
        $user ? ['My account', route('account.edit'), 'heroicon-o-user-circle', request()->routeIs('account.*')] : null,
        ['Info', route('info'), 'heroicon-o-information-circle', request()->routeIs('info', 'terms', 'privacy')],
        ['Help', route('help'), 'heroicon-o-question-mark-circle', request()->routeIs('help')],
        $user?->canAccessPanel(Filament::getPanel('admin')) ? ['Admin panel', url('/admin'), 'heroicon-o-shield-check', false] : null,
    ]);
    $cssVersion = @filemtime(public_path('css/app.css')) ?: null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Greens' }} - Bowls Buddy - {{ app(Settings::class)->get('client.name.full') }}</title>
    <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f172a" media="(prefers-color-scheme: dark)">
    <script>
        // The admin panel's theme choice (light, dark or the device's), so both halves match.
        (function () {
            var theme = 'system';
            try { theme = localStorage.getItem('theme') || 'system'; } catch (e) {}
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('fonts/filament/filament/inter/index.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}{{ $cssVersion ? '?v='.$cssVersion : '' }}">
    @if ($clubLogo = \App\Support\ClubLogo::url())
        <link rel="icon" href="{{ $clubLogo }}">
    @endif
</head>
<body>
    <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true" tabindex="-1">

    <header class="topbar">
        <label for="nav-toggle" class="nav-button" aria-label="Menu" title="Menu">
            {{ svg('heroicon-o-bars-3', 'icon') }}
        </label>
        <a href="{{ route('home') }}" class="brand">@include('filament.brand')</a>
        <div class="topbar-end">
            @auth
                <a href="{{ route('account.edit') }}" class="avatar" title="My account">
                    <img src="{{ Filament::getUserAvatarUrl($user) }}" alt="My account">
                </a>
            @elseif (! request()->routeIs('login'))
                <a href="{{ route('login') }}" class="button small">Log in</a>
            @endauth
        </div>
    </header>

    <label for="nav-toggle" class="nav-backdrop" aria-hidden="true"></label>

    <div class="layout">
        <aside class="sidebar">
            <div class="sidebar-head">
                <a href="{{ route('home') }}" class="brand">@include('filament.brand')</a>
                <label for="nav-toggle" class="nav-close" aria-label="Close menu" title="Close menu">
                    {{ svg('heroicon-o-x-mark', 'icon') }}
                </label>
            </div>
            <nav class="site" aria-label="Menu">
                <ul>
                    @foreach ($menu as [$label, $href, $icon, $active])
                        <li>
                            <a href="{{ $href }}" @class(['active' => $active]) @if ($active) aria-current="page" @endif>
                                {{ svg($icon, 'icon') }}<span>{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <ul class="nav-end">
                    @auth
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit">{{ svg('heroicon-o-arrow-left-end-on-rectangle', 'icon') }}<span>Log out</span></button>
                            </form>
                        </li>
                    @else
                        <li><a href="{{ route('login') }}" @class(['active' => request()->routeIs('login')])>{{ svg('heroicon-o-arrow-right-end-on-rectangle', 'icon') }}<span>Log in</span></a></li>
                        <li><a href="{{ route('register') }}" @class(['active' => request()->routeIs('register')])>{{ svg('heroicon-o-user-plus', 'icon') }}<span>Register</span></a></li>
                    @endauth
                </ul>
            </nav>
        </aside>

        <main class="{{ ($narrow ?? false) ? 'narrow' : '' }}">
            @if (session('status'))
                <div class="flash">{{ svg('heroicon-o-check-circle', 'icon') }}<div>{!! session('status') !!}</div></div>
            @endif
            @if (session('warning'))
                <div class="flash warning">{{ svg('heroicon-o-exclamation-triangle', 'icon') }}<div>{{ session('warning') }}</div></div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
