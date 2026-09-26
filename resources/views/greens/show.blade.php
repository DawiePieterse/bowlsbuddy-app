@extends('layouts.app', ['title' => 'Green '.$green])

@section('content')
    <div class="card">
        <div class="calendar-nav">
            @if ($previousDay)
                <a class="button subtle" href="{{ route('greens.show', [$green, $previousDay->format('Y-m-d')]) }}">&larr; {{ $previousDay->format('D j M') }}</a>
            @endif
            <div class="spacer"></div>
            @foreach ($greens as $other)
                @if ($other !== $green)
                    <a class="button subtle" href="{{ route('greens.show', [$other, $day->format('Y-m-d')]) }}">Green {{ $other }}</a>
                @endif
            @endforeach
            <div class="spacer"></div>
            @if ($nextDay)
                <a class="button subtle" href="{{ route('greens.show', [$green, $nextDay->format('Y-m-d')]) }}">{{ $nextDay->format('D j M') }} &rarr;</a>
            @endif
        </div>

        <h1>Green {{ $green }} &middot; {{ $day->format('l j F Y') }}</h1>

        @if ($hidden)
            <p class="muted">This day is not open for booking.</p>
        @elseif ($closed)
            <div class="flash warning">Green {{ $green }} is closed on this day.</div>
        @endif

        @guest
            <p class="muted"><a href="{{ route('login') }}">Log in</a> to book a rink and see who is playing.</p>
        @endguest

        <div style="overflow-x: auto;">
            <table class="calendar">
                <thead>
                    <tr>
                        <th class="hour">Time</th>
                        @foreach ($rinks as $rink)
                            <th>{{ $rink->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grid as $row)
                        <tr>
                            <th class="hour">{{ $row['time'] }}</th>
                            @foreach ($row['cells'] as $cell)
                                <td>
                                    @if ($cell['state'] === 'free')
                                        @auth
                                            <a class="slot-free" href="{{ $cell['url'] }}">Book</a>
                                        @else
                                            <a class="slot-free" href="{{ route('login') }}">Free</a>
                                        @endauth
                                    @elseif ($cell['state'] === 'own')
                                        <span class="slot-busy slot-own">{{ $cell['label'] }}</span>
                                    @elseif ($cell['state'] === 'booked')
                                        <span class="slot-busy">{{ $cell['label'] }}</span>
                                    @elseif ($cell['state'] === 'event')
                                        <span class="slot-busy slot-event">{{ $cell['label'] }}</span>
                                    @elseif ($cell['state'] === 'closed')
                                        <span class="slot-busy">Closed</span>
                                    @else
                                        <span class="slot-busy slot-past">&mdash;</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
