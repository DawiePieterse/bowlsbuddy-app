<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Bowls Buddy' }} - {{ app(\App\Support\Settings::class)->get('client.name.full', 'Bowls Buddy') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @if ($clubLogo = \App\Support\ClubLogo::url())
        <link rel="icon" href="{{ $clubLogo }}">
    @endif
</head>
<body>
    <header class="site">
        <a href="{{ route('home') }}" class="brand">
            @if ($clubLogo)
                <img class="logo" src="{{ $clubLogo }}" alt="">
            @endif
            <span>
                <span class="name">Bowls Buddy</span>
                <span class="club">{{ app(\App\Support\Settings::class)->get('client.name.full') }}</span>
            </span>
        </a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-hidden="true">
        <label for="nav-toggle" class="nav-button" aria-label="Menu">&#9776;</label>
        <nav class="site">
            <a href="{{ route('home') }}">Greens</a>
            <a href="{{ route('info') }}">Info</a>
            <a href="{{ route('help') }}">Help</a>
            @auth
                <a href="{{ route('bookings.index') }}">My bookings</a>
                <a href="{{ route('account.edit') }}">My account</a>
                @if (auth()->user()->canAccessPanel(filament()->getPanel('admin')))
                    <a href="{{ url('/admin') }}">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('register') }}">Register</a>
            @endauth
        </nav>
    </header>
    <main class="{{ ($narrow ?? false) ? 'narrow' : '' }}">
        @if (session('status'))
            <div class="flash">{!! session('status') !!}</div>
        @endif
        @if (session('warning'))
            <div class="flash warning">{{ session('warning') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
