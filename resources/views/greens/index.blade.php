@extends('layouts.app', ['title' => 'Greens'])

@section('content')
    <div class="page-header">
        <h1>Greens</h1>
        <p>The next 14 playing days. Tap a green to see its rinks and book a slot.</p>

        @auth
            @if (auth()->user()->hasPrivilege('admin.user'))
                @php($inviteText = 'Join '.app(\App\Support\Settings::class)->get('client.name.full', 'our club').' on Bowls Buddy and book your practice rink online: '.route('register'))
                <div class="actions">
                    <a class="button whatsapp" style="margin-top: 0;" target="_blank" rel="noopener"
                       href="{{ \App\Support\WhatsApp::share($inviteText) }}">@include('partials.whatsapp-icon')Invite members via WhatsApp</a>
                </div>
            @endif
        @endauth
    </div>

    <div class="card">
        @foreach ($days as $day)
            <div class="overview-day">
                <div class="date">
                    {{ $day['date']->format('D j M') }}
                    @if ($day['date']->isToday())
                        <span class="when">Today</span>
                    @elseif ($day['date']->isTomorrow())
                        <span class="when">Tomorrow</span>
                    @endif
                </div>
                <div class="greens">
                    @foreach ($day['slots'] as $green => $total)
                        <div class="green-cell">
                            @if ($day['closed'][$green])
                                <span class="green-pill closed"><span class="name">Green {{ $green }}</span> <span class="slots">closed</span></span>
                            @else
                                <a @class([
                                       'green-pill',
                                       'event' => $day['events'][$green],
                                       'full' => ! $day['events'][$green] && $day['free'][$green] === 0 && ! $day['opens'][$green],
                                       'opens' => $day['opens'][$green],
                                   ])
                                   href="{{ route('greens.show', [$green, $day['date']->format('Y-m-d')]) }}">
                                    <span class="name">Green {{ $green }}</span>
                                    @if ($day['opens'][$green])
                                        <span class="slots">booking opens {{ $day['opens'][$green]->format('D j M, H:i') }}</span>
                                    @else
                                        <span class="slots">{{ $day['free'][$green] }} of {{ $total }} free</span>
                                        <span class="meter" aria-hidden="true"><span style="width: {{ $total > 0 ? round($day['free'][$green] / $total * 100) : 0 }}%;"></span></span>
                                    @endif
                                    @if ($direction = app(\App\Services\GreenService::class)->directionLabel($green, $day['date']))
                                        <span class="direction"><x-direction-arrow :direction="$direction" /> {{ $direction }}</span>
                                    @endif
                                    @if ($day['events'][$green])
                                        <span class="events">{{ implode(', ', $day['events'][$green]) }}</span>
                                    @endif
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach

        @if (! $days)
            <div class="empty">
                {{ svg('heroicon-o-calendar', 'icon') }}
                <p class="muted">No playing days are open for booking at the moment.</p>
            </div>
        @endif
    </div>
@endsection
