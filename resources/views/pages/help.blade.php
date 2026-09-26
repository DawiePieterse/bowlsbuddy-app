@extends('layouts.app', ['title' => 'Help', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Help</h1>
        @if ($text = app(\App\Support\Settings::class)->get('service.help'))
            {!! $text !!}
        @else
            <h2>How to book</h2>
            <p>Pick a day and green on the <a href="{{ route('home') }}">greens overview</a>, tap a free
                slot, choose 1 or 2 players and confirm. One rink per member per day.</p>
            <h2>How to cancel</h2>
            <p>Under <a href="{{ route('bookings.index') }}">My bookings</a>, up to the cancel cut-off
                before the start.</p>
            <h2>Forgot your password?</h2>
            <p>Ask the Club Secretary to set a temporary one for you.</p>
        @endif
    </div>
@endsection
