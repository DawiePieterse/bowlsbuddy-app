@extends('layouts.app', ['title' => 'Booking confirmed', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Rink {{ $booking->rink->name }} is yours</h1>

        <p><strong>{{ $start->format('l j F Y') }}</strong>,
            {{ $start->format('H:i') }}&ndash;{{ substr($booking->reservations->first()->time_end, 0, 5) }}</p>

        @if ($booking->playerNames())
            <p>Playing with {{ implode(', ', $booking->playerNames()) }}.</p>
        @endif

        <a class="button whatsapp full" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Share on WhatsApp</a>

        <p style="margin-top: 20px;">
            <a href="{{ route('greens.show', [$booking->rink->green(), $start->format('Y-m-d')]) }}">Back to Green {{ $booking->rink->green() }}</a>
            &middot; <a href="{{ route('bookings.index') }}">My bookings</a>
        </p>
    </div>
@endsection
