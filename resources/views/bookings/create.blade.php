@extends('layouts.app', ['title' => 'Book a rink', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Book rink {{ $rink->name }}</h1>
        <p><strong>{{ $start->format('l j F Y') }}</strong>, {{ $start->format('H:i') }}&ndash;{{ $end->format('H:i') }}</p>

        @if ($reason !== null)
            <div class="flash warning">{{ $reason }}</div>
            <a class="button subtle" href="{{ route('greens.show', [$rink->green(), $start->format('Y-m-d')]) }}">Back to Green {{ $rink->green() }}</a>
        @else
            <form method="POST" action="{{ route('bookings.store') }}">
                @csrf
                <input type="hidden" name="rink" value="{{ $rink->sid }}">
                <input type="hidden" name="start" value="{{ $start->format('Y-m-d H:i') }}">

                <label for="players">Players</label>
                <select id="players" name="players">
                    @for ($count = 1; $count <= $maxPlayers; $count++)
                        <option value="{{ $count }}" @selected((int) old('players', 2) === $count)>{{ $count }}</option>
                    @endfor
                </select>
                @error('players') <div class="error">{{ $message }}</div> @enderror

                <label for="partner">Your partner's full name (when playing as 2)</label>
                <input id="partner" type="text" name="partner" value="{{ old('partner') }}" placeholder="First and last name">
                @error('partner') <div class="error">{{ $message }}</div> @enderror

                @if ($rulesText)
                    <div class="card" style="background: var(--bg); box-shadow: none; margin-top: 16px;">{!! $rulesText !!}</div>
                    <label class="check">
                        <input type="checkbox" name="accept_rules" value="1" @checked(old('accept_rules'))>
                        I have read and accept the rules above.
                    </label>
                    @error('accept_rules') <div class="error">{{ $message }}</div> @enderror
                @endif

                <p class="muted">One rink per member per day. You can cancel up to
                    {{ (int) round(($rink->range_cancel ?? 0) / 3600) }} hours before the start.</p>

                <button type="submit" class="full">Book this rink</button>
            </form>
        @endif
    </div>
@endsection
