<x-filament-widgets::widget>
    @php($birthdays = $this->birthdays())

    {{-- The panel's CSS is precompiled, so the list brings its own few rules. --}}
    <style>
        .bb-list { margin: 0; padding: 0; list-style: none; }
        .bb-list li { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem 1rem; padding: 0.75rem 0; border-top: 1px solid rgb(0 0 0 / 0.06); }
        .bb-list li:first-child { border-top: 0; padding-top: 0; }
        .bb-who { min-width: 0; flex: 1 1 12rem; font-size: 0.875rem; line-height: 1.25rem; }
        .bb-who strong { font-weight: 600; }
        .bb-muted { color: rgb(100 116 139); }
        .dark .bb-list li { border-top-color: rgb(255 255 255 / 0.08); }
        .dark .bb-muted { color: rgb(148 163 184); }
    </style>

    <x-filament::section icon="heroicon-o-cake" heading="Birthdays" description="Today and the coming week. Send opens WhatsApp with your wishes ready.">
        @if ($birthdays)
            <ul class="bb-list">
                @foreach ($birthdays as $birthday)
                    @php($today = $birthday['day']->isToday())
                    <li wire:key="birthday-{{ $birthday['user']->uid }}-{{ $birthday['day']->format('md') }}">
                        <div class="bb-who">
                            <strong>{{ $birthday['name'] }}</strong>
                            <span class="bb-muted">
                                &middot; {{ $today ? 'today' : $birthday['day']->format('D j M') }}@if ($birthday['age'] !== null), turns {{ $birthday['age'] }}@endif
                            </span>
                        </div>
                        @if ($birthday['wished'])
                            <x-filament::badge color="success" icon="heroicon-o-check">Wished</x-filament::badge>
                        @elseif ($today && $birthday['url'])
                            <x-filament::button tag="a" :href="$birthday['url']" target="_blank" rel="noopener" size="sm"
                                                icon="heroicon-o-chat-bubble-left-ellipsis"
                                                wire:click="markWished({{ $birthday['user']->uid }})">
                                Send wishes
                            </x-filament::button>
                        @elseif ($today)
                            <span class="bb-muted" style="font-size: 0.8125rem;">No cellphone number</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="bb-muted" style="font-size: 0.875rem;">No birthdays this week. Add members' birthdays on their page under Members.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
