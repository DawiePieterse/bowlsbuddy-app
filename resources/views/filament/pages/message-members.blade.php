<x-filament-panels::page>
    {{-- Shared bb-* helpers are in public/css/panel.css; this page adds the message preview. --}}
    <style>
        .bb-preview { margin-top: 0.25rem; font-size: 0.8125rem; line-height: 1.25rem; white-space: pre-line; overflow-wrap: anywhere; }
    </style>

    <form wire:submit="prepare">
        {{ $this->form }}

        <div class="bb-row" style="margin-top: 1.5rem;">
            <x-filament::button type="submit" icon="heroicon-o-chat-bubble-left-ellipsis">
                Prepare messages
            </x-filament::button>
            <span class="bb-small bb-muted">Each member gets their own copy to send with one tap from your WhatsApp.</span>
        </div>
    </form>

    @if ($prepared)
        @php($messages = $this->messages())

        {{-- Which messages were sent is kept in the browser only, so tapping Send doesn't reload the list. --}}
        <div x-data="{ sent: [] }">
        <x-filament::section
            :heading="count($messages['send']).' '.Str::plural('message', count($messages['send'])).' to send'"
            description="Tap Send to open WhatsApp with the message ready."
        >
            <div class="bb-row" style="margin-bottom: 1rem;">
                <x-filament::button tag="a" :href="$messages['group']" target="_blank" rel="noopener" color="gray" size="sm"
                                    icon="heroicon-o-user-group">
                    Send to a group instead
                </x-filament::button>
                <span class="bb-small bb-muted">One copy, with "everyone" for the name, to a club WhatsApp group.</span>
            </div>

            @if ($messages['send'])
                <p class="bb-small bb-muted" style="margin: 0 0 0.5rem;" x-cloak x-show="sent.length" x-text="sent.length + ' sent so far.'"></p>
                <ul class="bb-list">
                    @foreach ($messages['send'] as $message)
                        <li wire:key="member-{{ $message['uid'] }}">
                            <div class="bb-who">
                                <strong>{{ $message['name'] }}</strong>
                                <span class="bb-small bb-muted">&middot; {{ $message['phone'] }}</span>
                                <div class="bb-preview bb-muted">{{ $message['text'] }}</div>
                            </div>
                            <div class="bb-row">
                                <x-filament::badge color="success" icon="heroicon-o-check" x-cloak x-show="sent.includes({{ $message['uid'] }})">Sent</x-filament::badge>
                                <x-filament::button tag="a" :href="$message['url']" target="_blank" rel="noopener"
                                                    icon="heroicon-o-chat-bubble-left-ellipsis"
                                                    x-on:click="sent.includes({{ $message['uid'] }}) || sent.push({{ $message['uid'] }})">
                                    Send
                                </x-filament::button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="bb-small bb-muted">None of these members has a cellphone number.</p>
            @endif
        </x-filament::section>
        </div>

        @if ($messages['without'])
            <x-filament::section heading="No cellphone number" description="Let these members know another way.">
                <ul class="bb-list">
                    @foreach ($messages['without'] as $member)
                        <li class="bb-small">{{ $member['name'] }} @if ($member['email'])<span class="bb-muted">&middot; {{ $member['email'] }}</span>@endif</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
    @endif
</x-filament-panels::page>
