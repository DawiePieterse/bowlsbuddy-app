@use('App\Services\DisplacedBookings')
@extends('layouts.app', ['title' => 'My bookings', 'narrow' => true])

@section('content')
    <div class="page-header">
        <h1>My bookings</h1>
    </div>

    <div class="card">
        @forelse ($bookings as $booking)
            @php($reservation = $booking->reservations->first())
            <div @class(['booking-row', 'is-cancelled' => $booking->status === 'cancelled'])>
                <div class="day" aria-hidden="true">
                    <b>{{ $reservation?->date->format('j') }}</b>
                    <small>{{ $reservation?->date->format('M') }}</small>
                </div>
                <div class="details {{ $booking->status === 'cancelled' ? 'cancelled' : '' }}">
                    <div class="when">{{ $reservation?->date->format('D j M Y') }}</div>
                    <div class="muted">
                        {{ substr($reservation?->time_start ?? '', 0, 5) }}&ndash;{{ substr($reservation?->time_end ?? '', 0, 5) }}
                        &middot; Rink {{ $booking->rink?->name }}@if ($booking->playerNames()), with {{ implode(', ', $booking->playerNames()) }}@endif
                        @if ($booking->status === 'cancelled') &middot; cancelled{{ ($closure = $booking->meta(DisplacedBookings::REASON)) ? ' because '.$closure : '' }} @endif
                    </div>
                </div>
                @if ($cancellable[$booking->bid])
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}"
                          onsubmit="return confirm('Cancel this booking?');">
                        @csrf
                        <button type="submit" class="subtle small warn">Cancel</button>
                    </form>
                @endif
            </div>
        @empty
            <div class="empty">
                {{ svg('heroicon-o-calendar-days', 'icon') }}
                <p class="muted">You have no bookings yet. Pick a slot from the <a href="{{ route('home') }}">greens overview</a>.</p>
            </div>
        @endforelse
    </div>
@endsection
