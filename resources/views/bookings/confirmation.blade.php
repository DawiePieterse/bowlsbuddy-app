@extends('layouts.app', ['title' => 'Booking confirmed', 'narrow' => true])

@section('content')
    <div class="card">
        <span class="success-mark">{{ svg('heroicon-o-check', 'icon') }}</span>
        <h1>Rink {{ $booking->rink->name }} is yours</h1>

        <ul class="summary" style="margin-top: 1.25rem;">
            <li>{{ svg('heroicon-o-calendar-days', 'icon') }}<strong>{{ $start->format('l j F Y') }}</strong></li>
            <li>{{ svg('heroicon-o-clock', 'icon') }}<span>{{ $start->format('H:i') }}&ndash;{{ substr($booking->reservations->first()->time_end, 0, 5) }} on Green {{ $booking->rink->green() }}</span></li>
            @if ($booking->playerNames())
                <li>{{ svg('heroicon-o-user-group', 'icon') }}<span>Playing with {{ implode(', ', $booking->playerNames()) }}.</span></li>
            @endif
        </ul>

        <a class="button whatsapp full" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">@include('partials.whatsapp-icon')Share on WhatsApp</a>

        <p class="links">
            <a href="{{ route('greens.show', [$booking->rink->green(), $start->format('Y-m-d')]) }}">Back to Green {{ $booking->rink->green() }}</a>
            <a href="{{ route('bookings.index') }}">My bookings</a>
        </p>
    </div>
@endsection
