@use('App\Services\DisplacedBookings')
@use('App\Support\Phone')

<x-filament-panels::page>
    @php($messages = $this->messages())

    {{-- The panel's CSS is precompiled, so the list brings its own few rules. --}}
    <style>
        .bb-items { margin: 0; padding: 0; list-style: none; font-size: 0.875rem; }
        .bb-items li + li { margin-top: 0.25rem; }
        .bb-message { margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; white-space: pre-line; background-color: rgb(0 0 0 / 0.04); }
        .bb-send { margin-top: 1rem; }
        .bb-muted { color: rgb(100 116 139); }
        .bb-small { font-size: 0.875rem; line-height: 1.25rem; }
        .dark .bb-message { background-color: rgb(255 255 255 / 0.05); }
        .dark .bb-muted { color: rgb(148 163 184); }
    </style>

    <p class="bb-small bb-muted">
        Upcoming bookings that can't go ahead because a green was closed for the day, an event took the rink,
        a rink was taken out of use or the club is closed that day. Send each member the message from your
        phone; you can change it in WhatsApp before you send it. The bookings stay in place, so reopening a
        green brings them back.
    </p>

    @forelse ($messages as $message)
        <x-filament::section
            :heading="$message['name']"
            :description="$message['phone'] ? Phone::pretty($message['phone']) : 'No cellphone number'.($message['user']->email ? ' · '.$message['user']->email : '')"
        >
            <ul class="bb-items">
                @foreach ($message['items'] as $item)
                    <li>{{ ucfirst(DisplacedBookings::describe($item)) }}: <span class="bb-muted">{{ $item['reason'] }}</span></li>
                @endforeach
            </ul>

            <div class="bb-message">{{ $message['text'] }}</div>

            @if ($message['url'])
                <div class="bb-send">
                    <x-filament::button tag="a" :href="$message['url']" target="_blank" rel="noopener"
                                        icon="heroicon-o-chat-bubble-left-ellipsis">
                        Send on WhatsApp
                    </x-filament::button>
                </div>
            @else
                <p class="bb-send bb-small bb-muted">Add a cellphone number to this member to message them on WhatsApp.</p>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="bb-small bb-muted">No upcoming bookings are affected. When a green is closed or an event takes
                a rink that members had booked, they show up here.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
