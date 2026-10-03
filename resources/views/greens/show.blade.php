@extends('layouts.app', ['title' => 'Green '.$green])

@section('content')
    <div class="page-header">
        <h1>Green {{ $green }} &middot; {{ $day->format('l j F Y') }}</h1>
        @if ($direction)
            <p class="direction-note"><x-direction-arrow :direction="$direction" /> Direction of play: <strong>{{ $direction }}</strong></p>
        @endif
    </div>

    <div class="calendar-nav">
        @if ($previousDay)
            <a class="button subtle small prev" href="{{ route('greens.show', [$green, $previousDay->format('Y-m-d')]) }}">{{ svg('heroicon-o-chevron-left', 'icon') }}{{ $previousDay->format('D j M') }}</a>
        @endif
        @if (count($greens) > 1)
            <nav class="tabs" aria-label="Greens">
                @foreach ($greens as $other)
                    <a href="{{ route('greens.show', [$other, $day->format('Y-m-d')]) }}" @class(['active' => $other === $green])
                       @if ($other === $green) aria-current="page" @endif>Green {{ $other }}</a>
                @endforeach
            </nav>
        @endif
        @if ($nextDay)
            <a class="button subtle small next" href="{{ route('greens.show', [$green, $nextDay->format('Y-m-d')]) }}">{{ $nextDay->format('D j M') }}{{ svg('heroicon-o-chevron-right', 'icon') }}</a>
        @endif
    </div>

    @if ($secretary)
        <div class="secretary-actions">
            <span class="label">{{ svg('heroicon-o-shield-check', 'icon') }} Secretary</span>
            @if ($closed)
                <form method="POST" action="{{ route('greens.open', [$green, $day->format('Y-m-d')]) }}">
                    @csrf
                    <button type="submit" class="subtle small">{{ svg('heroicon-o-lock-open', 'icon') }}Open green {{ $green }} on this day</button>
                </form>
            @else
                <form method="POST" action="{{ route('greens.close', [$green, $day->format('Y-m-d')]) }}"
                      onsubmit="return confirm(@js('Close green '.$green.' on '.$day->format('D j M').'?'.($bookedCount > 0 ? ' Its '.$bookedCount.' '.Str::plural('booking', $bookedCount).' will be cancelled.' : '')));">
                    @csrf
                    <button type="submit" class="danger small">{{ svg('heroicon-o-lock-closed', 'icon') }}Close green {{ $green }} on this day</button>
                </form>
            @endif
            <form method="POST" action="{{ route('greens.direction', [$green, $day->format('Y-m-d')]) }}" class="direction-form">
                @csrf
                <select name="direction" aria-label="Direction of play">
                    <option value="">Direction of play...</option>
                    @foreach (\App\Services\GreenService::DIRECTIONS as $value => $label)
                        <option value="{{ $value }}" @selected(app(\App\Services\GreenService::class)->direction($green, $day) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="subtle small">Set</button>
            </form>
            <a class="button subtle small" target="_blank"
               href="{{ route('greens.sheet', [$green, $day->format('Y-m-d')]) }}">{{ svg('heroicon-o-printer', 'icon') }}Day sheet</a>
        </div>
    @endif

    @if ($hidden)
        <div class="flash warning">This day is not open for booking.</div>
    @elseif ($closed)
        <div class="flash warning">{{ svg('heroicon-o-lock-closed', 'icon') }}<div>Green {{ $green }} is closed on this day.</div></div>
    @endif

    @if ($affected)
        @include('greens.partials.affected', ['messages' => $affected])
    @endif

    @guest
        <div class="flash">{{ svg('heroicon-o-information-circle', 'icon') }}<div><a href="{{ route('login') }}">Log in</a> to book a rink and see who is playing.</div></div>
    @endguest

    <div class="card">
        <p class="calendar-key" aria-hidden="true">
            <span><i class="key key-free"></i>Free</span>
            <span><i class="key key-booked"></i>Booked</span>
            @auth<span><i class="key key-own"></i>Yours</span>@endauth
            <span><i class="key key-event"></i>Event</span>
        </p>

        <div class="calendar-scroll">
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
                        @php([$from, $until] = array_pad(explode('-', $row['time'], 2), 2, ''))
                        <tr>
                            <th class="hour">{{ $from }}<span class="until"><span class="sr-only">to </span>{{ $until }}</span></th>
                            @foreach ($row['cells'] as $cell)
                                <td>
                                    @if ($cell['state'] === 'free')
                                        @auth
                                            <a class="slot-free" href="{{ $cell['url'] }}"><span class="slot-text">Book</span></a>
                                        @else
                                            <a class="slot-free" href="{{ route('login') }}"><span class="slot-text">Free</span></a>
                                        @endauth
                                    @elseif ($cell['state'] === 'own' || $cell['state'] === 'booked')
                                        <span class="slot-busy {{ $cell['state'] === 'own' ? 'slot-own' : '' }}">
                                            @forelse ($cell['names'] as $name)
                                                <span class="player slot-text">{{ $name }}</span>
                                            @empty
                                                <span class="slot-text">Booked</span>
                                            @endforelse
                                        </span>
                                    @elseif ($cell['state'] === 'event')
                                        <span class="slot-busy slot-event"><span class="slot-text">{{ $cell['label'] }}</span></span>
                                    @elseif ($cell['state'] === 'closed')
                                        <span class="slot-busy slot-closed"><span class="slot-text">Closed</span></span>
                                    @else
                                        <span class="slot-busy slot-past"><span class="slot-text">&mdash;</span></span>
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
