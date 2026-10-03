<x-filament-panels::page>
    {{-- The panel's CSS is precompiled, so the list brings its own few rules. --}}
    <style>
        .bb-list { margin: 0; padding: 0; list-style: none; }
        .bb-list li { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; padding: 0.75rem 0; border-top: 1px solid rgb(0 0 0 / 0.06); }
        .bb-list li:first-child { border-top: 0; padding-top: 0; }
        .bb-who { min-width: 0; flex: 1 1 14rem; }
        .bb-who strong { font-weight: 600; }
        .bb-preview { margin-top: 0.25rem; font-size: 0.8125rem; line-height: 1.25rem; white-space: pre-line; overflow-wrap: anywhere; }
        .bb-muted { color: rgb(100 116 139); }
        .bb-small { font-size: 0.875rem; line-height: 1.25rem; }
        .bb-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; }
        .dark .bb-list li { border-top-color: rgb(255 255 255 / 0.08); }
        .dark .bb-muted { color: rgb(148 163 184); }
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

        <x-filament::section
            :heading="count($messages['send']).' '.Str::plural('message', count($messages['send'])).' to send'"
            :description="count(array_filter(array_column($messages['send'], 'sent'))).' sent so far. Tap Send to open WhatsApp with the message ready.'"
        >
            <div class="bb-row" style="margin-bottom: 1rem;">
                <x-filament::button tag="a" :href="$messages['group']" target="_blank" rel="noopener" color="gray" size="sm"
                                    icon="heroicon-o-user-group">
                    Send to a group instead
                </x-filament::button>
                <span class="bb-small bb-muted">One copy, with "everyone" for the name, to a club WhatsApp group.</span>
            </div>

            @if ($messages['send'])
                <ul class="bb-list">
                    @foreach ($messages['send'] as $message)
                        <li wire:key="member-{{ $message['uid'] }}">
                            <div class="bb-who">
                                <strong>{{ $message['name'] }}</strong>
                                <span class="bb-small bb-muted">&middot; {{ $message['phone'] }}</span>
                                <div class="bb-preview bb-muted">{{ $message['text'] }}</div>
                            </div>
                            <x-filament::button tag="a" :href="$message['url']" target="_blank" rel="noopener"
                                                :color="$message['sent'] ? 'gray' : 'primary'"
                                                :icon="$message['sent'] ? 'heroicon-o-check' : 'heroicon-o-chat-bubble-left-ellipsis'"
                                                wire:click="markSent({{ $message['uid'] }})">
                                {{ $message['sent'] ? 'Sent' : 'Send' }}
                            </x-filament::button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="bb-small bb-muted">None of these members has a cellphone number.</p>
            @endif
        </x-filament::section>

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
