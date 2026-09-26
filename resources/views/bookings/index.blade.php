@extends('layouts.app', ['title' => 'My bookings', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>My bookings</h1>

        @forelse ($bookings as $booking)
            @php($reservation = $booking->reservations->first())
            <div class="booking-row">
                <div class="{{ $booking->status === 'cancelled' ? 'cancelled' : '' }}">
                    <div class="when">
                        {{ $reservation?->date->format('D j M Y') }},
                        {{ substr($reservation?->time_start ?? '', 0, 5) }}&ndash;{{ substr($reservation?->time_end ?? '', 0, 5) }}
                    </div>
                    <div class="muted">
                        Rink {{ $booking->rink?->name }}@if ($booking->playerNames()), with {{ implode(', ', $booking->playerNames()) }}@endif
                        @if ($booking->status === 'cancelled') &middot; cancelled @endif
                    </div>
                </div>
                @if ($cancellable[$booking->bid])
                    <form method="POST" action="{{ route('bookings.cancel', $booking) }}"
                          onsubmit="return confirm('Cancel this booking?');">
                        @csrf
                        <button type="submit" class="danger">Cancel</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="muted">You have no bookings yet. Pick a slot from the <a href="{{ route('home') }}">greens overview</a>.</p>
        @endforelse
    </div>
@endsection
