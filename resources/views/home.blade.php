@extends('layouts.app', ['title' => 'Greens'])

@section('content')
    <div class="card">
        <h1>Bowls Buddy</h1>

        @auth
            <p>Logged in as <strong>{{ auth()->user()->alias }}</strong>.</p>
            @if (auth()->user()->canAccessPanel(filament()->getPanel('admin')))
                <p><a href="{{ url('/admin') }}">Administration</a></p>
            @endif
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">Log out</button>
            </form>
        @else
            <p class="muted">The greens and rink bookings are being rebuilt.</p>
            <p><a href="{{ route('login') }}">Log in</a></p>
        @endauth
    </div>
@endsection
