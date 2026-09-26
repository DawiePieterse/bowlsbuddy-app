<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Bowls Buddy' }} - {{ app(\App\Support\Settings::class)->get('client.name.full', 'Bowls Buddy') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="site">
        <a href="{{ route('home') }}">
            <div class="club">{{ app(\App\Support\Settings::class)->get('client.name.full') }}</div>
            <div class="name">Bowls Buddy</div>
        </a>
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
