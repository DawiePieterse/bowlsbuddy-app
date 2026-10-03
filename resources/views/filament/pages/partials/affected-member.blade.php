@use('App\Services\DisplacedBookings')
@use('App\Support\Phone')
{{-- One member on the Affected bookings page: their cancelled bookings, the message and how to send it. --}}
<x-filament::section
    :heading="$message['name']"
    :description="$message['phone'] ? Phone::pretty($message['phone']) : 'No cellphone number'.($message['user']->email ? ' · '.$message['user']->email : '')"
    :icon="$message['told'] ? 'heroicon-o-check-circle' : null"
    :icon-color="$message['told'] ? 'success' : 'gray'"
>
    <ul class="bb-items">
        @foreach ($message['items'] as $item)
            <li>{{ ucfirst(DisplacedBookings::describe($item)) }}: <span class="bb-muted">{{ $item['reason'] }}</span></li>
        @endforeach
    </ul>

    <div class="bb-message">{{ $message['text'] }}</div>

    <div class="bb-send bb-row">
        @if ($message['url'])
            <x-filament::button tag="a" :href="$message['url']" target="_blank" rel="noopener"
                                :color="$message['told'] ? 'gray' : 'primary'"
                                icon="heroicon-o-chat-bubble-left-ellipsis"
                                wire:click="markTold('{{ $message['bookings'] }}')">
                {{ $message['told'] ? 'Send again' : 'Send on WhatsApp' }}
            </x-filament::button>
        @endif
        @unless ($message['told'])
            <x-filament::button color="gray" wire:click="markTold('{{ $message['bookings'] }}')">
                Mark as told
            </x-filament::button>
        @endunless
        @unless ($message['url'])
            <span class="bb-small bb-muted">No cellphone number: let them know another way, then mark them as told.</span>
        @endunless
    </div>
</x-filament::section>
