@extends('layouts.app', ['title' => 'Greens'])

@section('content')
    <div class="card">
        <h1>Greens</h1>
        <p class="muted">The next 14 playing days. Tap a green to see its rinks and book a slot.</p>

        @auth
            @if (auth()->user()->hasPrivilege('admin.user'))
                @php($inviteText = 'Join '.app(\App\Support\Settings::class)->get('client.name.full', 'our club').' on Bowls Buddy and book your practice rink online: '.route('register'))
                <p><a class="button whatsapp" style="margin-top: 0;" target="_blank" rel="noopener"
                      href="https://wa.me/?text={{ rawurlencode($inviteText) }}">Invite members via WhatsApp</a></p>
            @endif
        @endauth

        @foreach ($days as $day)
            <div class="overview-day">
                <div class="date">{{ $day['date']->format('D j M') }}</div>
                @foreach ($day['greens'] as $green => $info)
                    <div class="green-cell">
                        @if ($info['closed'])
                            <span class="green-pill closed">Green {{ $green }} <span class="slots">closed</span></span>
                        @else
                            <a class="green-pill {{ $info['events'] ? 'event' : ($info['free'] === 0 ? 'full' : '') }}"
                               href="{{ route('greens.show', [$green, $day['date']->format('Y-m-d')]) }}">
                                Green {{ $green }}
                                <span class="slots">{{ $info['free'] }} of {{ $info['total'] }} free</span>
                                @if ($info['events'])
                                    <span class="events">{{ implode(', ', $info['events']) }}</span>
                                @endif
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        @if (! $days)
            <p class="muted">No playing days are open for booking at the moment.</p>
        @endif
    </div>
@endsection
