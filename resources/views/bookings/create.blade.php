@extends('layouts.app', ['title' => 'Book a rink', 'narrow' => true])

@section('content')
    <div class="page-header">
        <h1>Book rink {{ $rink->name }}</h1>
    </div>

    <div class="card">
        <ul class="summary">
            <li>{{ svg('heroicon-o-calendar-days', 'icon') }}<strong>{{ $start->format('l j F Y') }}</strong></li>
            <li>{{ svg('heroicon-o-clock', 'icon') }}<span>{{ $start->format('H:i') }}&ndash;{{ $end->format('H:i') }} on Green {{ $rink->green() }}</span></li>
            @if ($direction = app(\App\Services\GreenService::class)->directionLabel($rink->green(), $start))
                <li><x-direction-arrow :direction="$direction" /><span>Direction of play: <strong>{{ $direction }}</strong></span></li>
            @endif
        </ul>
    </div>

    @if ($reason !== null)
        <div class="flash warning">{{ svg('heroicon-o-exclamation-triangle', 'icon') }}<div>{{ $reason }}</div></div>
        <a class="button subtle" style="margin-top: 0;" href="{{ route('greens.show', [$rink->green(), $start->format('Y-m-d')]) }}">{{ svg('heroicon-o-chevron-left', 'icon') }}Back to Green {{ $rink->green() }}</a>
    @else
        <div class="card">
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
                    <div class="note prose">{!! $rulesText !!}</div>
                    <label class="check">
                        <input type="checkbox" name="accept_rules" value="1" @checked(old('accept_rules'))>
                        I have read and accept the rules above.
                    </label>
                    @error('accept_rules') <div class="error">{{ $message }}</div> @enderror
                @endif

                <p class="muted" style="margin-bottom: 0;">One rink per member per day.
                    @if ($rink->range_cancel === null)
                        Bookings cannot be cancelled online.
                    @elseif ((int) $rink->range_cancel === 0)
                        You can cancel until the slot starts.
                    @else
                        You can cancel up to {{ (int) round($rink->range_cancel / 3600) }} hours before the start.
                    @endif</p>

                <button type="submit" class="full">Book this rink</button>
            </form>
        </div>
    @endif
@endsection
