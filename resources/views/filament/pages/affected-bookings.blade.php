<x-filament-panels::page>
    @php
        $messages = $this->messages();
        $toTell = array_values(array_filter($messages, fn (array $message) => ! $message['told']));
        $told = array_values(array_filter($messages, fn (array $message) => $message['told']));
    @endphp

    {{-- Shared bb-* helpers are in public/css/panel.css; this page adds the booking list and message box. --}}
    <style>
        .bb-items { margin: 0; padding: 0; list-style: none; font-size: 0.875rem; }
        .bb-items li + li { margin-top: 0.25rem; }
        .bb-message { margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; white-space: pre-line; overflow-wrap: anywhere; background-color: rgb(0 0 0 / 0.04); }
        .bb-send { margin-top: 1rem; }
        .bb-heading { margin: 0.5rem 0 0; font-size: 1rem; font-weight: 600; }
        .dark .bb-message { background-color: rgb(255 255 255 / 0.05); }
    </style>

    <p class="bb-small bb-muted">
        Upcoming bookings cancelled because a green was closed for the day, an event took the rink, a rink was
        taken out of use or the club is closed that day. Send each member the message from your phone; you can
        change it in WhatsApp before you send it. Sending it marks the member as told.
    </p>

    @forelse ($toTell as $message)
        @include('filament.pages.partials.affected-member', ['message' => $message])
    @empty
        <x-filament::section>
            <p class="bb-small bb-muted">
                {{ $told ? 'Everyone has been told.' : 'No upcoming bookings were cancelled by a closure.' }}
                When a green is closed or an event takes a rink that members had booked, their bookings are cancelled
                and show up here.
            </p>
        </x-filament::section>
    @endforelse

    @if ($told)
        <h2 class="bb-heading">Already told</h2>

        @foreach ($told as $message)
            @include('filament.pages.partials.affected-member', ['message' => $message])
        @endforeach
    @endif
</x-filament-panels::page>
