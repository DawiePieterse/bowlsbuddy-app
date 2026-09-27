@use('App\Filament\Pages\Utilisation')
@use('App\Services\RinkUtilisation')

@php
    [$from, $until] = $page->usageRange();
    $totals = $page->usageTotals();
@endphp

{{-- The period filter of the "Use of rinks" tab, with everyone's totals the shares are measured against. --}}
<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem 1rem; padding: 1rem 1.5rem;">
    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;" role="group" aria-label="Period">
        @foreach (RinkUtilisation::PERIODS as $key => $label)
            <x-filament::button
                size="sm"
                :color="$key === $page->usagePeriod ? 'primary' : 'gray'"
                wire:click="setUsagePeriod('{{ $key }}')"
                :aria-pressed="$key === $page->usagePeriod ? 'true' : 'false'"
            >
                {{ $label }}
            </x-filament::button>
        @endforeach
    </div>

    <p class="fi-ta-header-description" style="margin: 0;">
        {{ $from->format('D j M Y') }} to {{ $until->format('D j M Y') }}:
        {{ Utilisation::formatHours($totals['hours']) }} h in {{ $totals['bookings'] }} {{ Str::plural('booking', $totals['bookings']) }}
        by {{ $totals['members'] }} {{ Str::plural('member', $totals['members']) }}.
        Counted by the member who booked; cancelled bookings are left out.
    </p>
</div>
