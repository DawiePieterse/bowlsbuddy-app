<x-filament-widgets::widget>
    @php($birthdays = $this->birthdays())

    <x-filament::section icon="heroicon-o-cake" heading="Birthdays" description="Today and the coming week. Send opens WhatsApp with your wishes ready.">
        @if ($birthdays)
            <ul class="bb-list">
                @foreach ($birthdays as $birthday)
                    @php($today = $birthday['day']->isToday())
                    <li wire:key="birthday-{{ $birthday['user']->uid }}-{{ $birthday['day']->format('md') }}">
                        <div class="bb-who bb-small">
                            <strong>{{ $birthday['name'] }}</strong>
                            <span class="bb-muted">
                                &middot; {{ $today ? 'today' : $birthday['day']->format('D j M') }}, turns {{ $birthday['age'] }}
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
                            <span class="bb-muted bb-small">No cellphone number</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="bb-muted bb-small">No birthdays this week. Add members' birthdays on their page under Members.</p>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
