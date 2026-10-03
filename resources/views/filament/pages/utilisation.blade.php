@use('App\Filament\Pages\Utilisation')

<x-filament-panels::page>
    @php
        $heatmap = $this->heatmap();
        $columns = $heatmap['columns'];
        $anyHours = collect($heatmap['greens'])->contains(fn (array $green) => $green['max'] > 0);
        $ramp = Utilisation::RAMP;
    @endphp

    {{-- The panel's CSS is precompiled, so the heatmap brings its own few rules. --}}
    <style>
        .bb-period { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; }
        .bb-period-buttons { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .bb-directions > * + * { margin-top: 1.5rem; }
        .bb-direction-heading { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 0.75rem; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 600; }
        .bb-direction-heading .bb-muted { font-weight: 400; }
        .bb-scroll { overflow-x: auto; }
        .bb-heatmap { width: 100%; border-collapse: separate; border-spacing: 2px; font-size: 0.75rem; line-height: 1rem; }
        .bb-heatmap th, .bb-heatmap td { padding: 0.25rem 0.375rem; white-space: nowrap; }
        .bb-heatmap thead th { text-align: center; font-weight: 400; color: rgb(107 114 128); }
        .bb-heatmap thead th:first-child { text-align: start; }
        .bb-heatmap thead th:first-child, .bb-heatmap thead th:last-child, .bb-heatmap tbody th, .bb-heatmap td.bb-total { font-weight: 600; color: inherit; }
        .bb-heatmap thead th:last-child, .bb-heatmap td.bb-total { text-align: end; }
        .bb-heatmap tbody th { text-align: start; }
        .bb-heatmap td.bb-cell { height: 2rem; min-width: 2rem; text-align: center; border-radius: 0.25rem; font-variant-numeric: tabular-nums; background-color: rgb(0 0 0 / 0.04); }
        .bb-heatmap td.bb-total { font-variant-numeric: tabular-nums; }
        .bb-heatmap--dense th, .bb-heatmap--dense td { padding: 0.25rem 0.125rem; }
        .bb-heatmap--dense td.bb-cell { min-width: 1.625rem; }
        .bb-heatmap thead th:first-child, .bb-heatmap tbody th { position: sticky; left: 0; z-index: 1; background-color: #fff; }
        .bb-heatmap thead th:last-child, .bb-heatmap td.bb-total { position: sticky; right: 0; z-index: 1; background-color: #fff; padding-inline-start: 0.5rem; }
        .bb-legend { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; font-size: 0.75rem; }
        .bb-swatch { display: inline-block; width: 1.5rem; height: 0.75rem; border-radius: 0.25rem; }
        .dark .bb-heatmap thead th { color: rgb(156 163 175); }
        .dark .bb-heatmap td.bb-cell { background-color: rgb(255 255 255 / 0.05); }
        .dark .bb-heatmap thead th:first-child, .dark .bb-heatmap tbody th, .dark .bb-heatmap thead th:last-child, .dark .bb-heatmap td.bb-total { background-color: #111827; }
    </style>

    <div class="bb-period">
        <div class="bb-period-buttons" role="group" aria-label="Period">
            @foreach ($this->periods() as $key => $label)
                <x-filament::button
                    size="sm"
                    :color="$key === $period ? 'primary' : 'gray'"
                    wire:click="setPeriod('{{ $key }}')"
                    :aria-pressed="$key === $period ? 'true' : 'false'"
                >
                    {{ $label }}
                </x-filament::button>
            @endforeach
        </div>

        <p class="text-sm bb-muted">
            {{ $heatmap['from']->format('D j M Y') }} to {{ $heatmap['until']->format('D j M Y') }},
            hours booked per rink.
        </p>
    </div>

    @if (! $anyHours)
        <p class="text-sm bb-muted">No bookings in this period.</p>
    @endif

    @foreach ($heatmap['greens'] as $green => $data)
        <x-filament::section
            heading="Green {{ $green }}"
            description="{{ $data['max'] > 0
                ? 'Shades are relative to the busiest cell on this green (' . Utilisation::formatHours($data['max']) . ' h). Days are counted when the green had a booking.'
                : 'No bookings on this green in this period.' }}"
        >
            <div class="bb-directions">
                @foreach ($data['directions'] as $direction => $usage)
                    @continue($direction === '' && $usage['total'] <= 0)

                    <div>
                        <h3 class="bb-direction-heading">
                            <span>{{ $this->directionLabel($direction) }}</span>
                            <span class="bb-muted">
                                {{ $usage['days'] }} {{ Str::plural('day', $usage['days']) }},
                                {{ Utilisation::formatHours($usage['total']) }} h
                            </span>
                        </h3>

                        @if ($usage['total'] <= 0)
                            <p class="text-sm bb-muted">
                                No bookings played {{ $this->directionLabel($direction) }} in this period.
                            </p>
                        @else
                            <div class="bb-scroll">
                                <table class="bb-heatmap {{ count($columns) > 14 ? 'bb-heatmap--dense' : '' }}">
                                    <thead>
                                        <tr>
                                            <th scope="col">Rink</th>
                                            @foreach ($columns as $column)
                                                <th scope="col">{{ $column['label'] }}</th>
                                            @endforeach
                                            <th scope="col">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($usage['rinks'] as $row)
                                            <tr>
                                                <th scope="row">{{ $row['rink']->name }}</th>
                                                @foreach ($columns as $column)
                                                    @php($hours = $row['hours'][$column['key']])
                                                    @php($shade = $this->shade($hours, $data['max']))
                                                    <td
                                                        class="bb-cell"
                                                        @if ($shade['background'] !== null)
                                                            style="background-color: {{ $shade['background'] }}; color: {{ $shade['ink'] }};"
                                                        @endif
                                                        title="{{ $row['rink']->name }}, {{ $column['label'] }}: {{ Utilisation::formatHours($hours) }} h"
                                                    >
                                                        {{ $hours > 0 ? Utilisation::formatHours($hours) : '' }}
                                                    </td>
                                                @endforeach
                                                <td class="bb-total">{{ Utilisation::formatHours($row['total']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endforeach

    @if ($anyHours)
        <div class="bb-legend bb-muted" aria-hidden="true">
            <span>Fewer hours</span>
            @foreach ($ramp as $swatch)
                <span class="bb-swatch" style="background-color: {{ $swatch }};"></span>
            @endforeach
            <span>More hours</span>
        </div>
    @endif

    <p class="text-sm bb-muted">
        The direction is the one indicated on the green's calendar for that day; days without one are
        listed separately. Cancelled bookings and events are not counted.
    </p>
</x-filament-panels::page>
