@use('App\Filament\Pages\Licence')
@use('App\Support\Licensing\Modules')

<x-filament-panels::page>
    @php
        $modules = app(Modules::class);
        $licence = $modules->licence();
        $problem = $modules->problem();
    @endphp

    {{-- The panel's CSS is precompiled, so the page brings its own few rules. --}}
    <style>
        .bb-facts { display: grid; grid-template-columns: max-content 1fr; gap: 0.375rem 1.5rem; font-size: 0.875rem; }
        .bb-facts dt { font-weight: 600; }
        .bb-modules { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .bb-modules th, .bb-modules td { padding: 0.5rem 0.5rem; text-align: start; vertical-align: top; border-top: 1px solid rgb(0 0 0 / 0.06); }
        .bb-modules thead th { border-top: 0; font-weight: 600; }
        .bb-modules td:last-child, .bb-modules th:last-child { width: 1%; text-align: end; white-space: nowrap; }
        .bb-modules .fi-badge, .bb-modules .fi-badge * { overflow: visible; text-overflow: clip; max-width: none; }
        .bb-muted { color: rgb(107 114 128); }
        .bb-problem { color: rgb(185 28 28); }
        .dark .bb-modules th, .dark .bb-modules td { border-top-color: rgb(255 255 255 / 0.08); }
        .dark .bb-modules thead th { border-top: 0; }
        .dark .bb-muted { color: rgb(156 163 175); }
        .dark .bb-problem { color: rgb(248 113 113); }
        @media (max-width: 640px) {
            .bb-modules .bb-description { display: none; }
        }
    </style>

    <x-filament::section heading="Your licence">
        @if ($problem !== null)
            <p class="bb-problem">The installed licence can't be used: {{ $problem }}</p>
        @endif

        @if ($licence === null)
            <p>No licence is installed, so the club has bookings only. Members can book as usual.</p>
        @else
            <dl class="bb-facts">
                <dt>Club</dt>
                <dd>{{ $licence->club }}</dd>
                <dt>Plan</dt>
                <dd>{{ $licence->plan !== '' ? ucfirst($licence->plan) : '-' }}@if ($licence->trial) (trial)@endif</dd>
                <dt>Paid until</dt>
                <dd>
                    {{ $licence->expires->format('j F Y') }}
                    @if ($licence->expires->isPast() && ! $licence->expires->isToday())
                        <span class="bb-problem">
                            (expired; modules turn read-only after {{ $modules->graceEnds($licence)->format('j F Y') }})
                        </span>
                    @endif
                </dd>
                <dt>Greens</dt>
                <dd>{{ $this->greensInUse() }} in use, {{ $licence->greens ?? 'no limit' }} paid for</dd>
            </dl>
        @endif
    </x-filament::section>

    <x-filament::section heading="Modules">
        <table class="bb-modules">
            <thead>
                <tr><th>Module</th><th class="bb-description">What it does</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($this->moduleRows() as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="bb-description bb-muted">{{ $row['description'] }}</td>
                        <td>
                            <x-filament::badge :color="Licence::badgeColour($row['state'])" style="display: inline-flex">
                                {{ $row['state']->label() }}
                            </x-filament::badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p class="text-sm bb-muted" style="margin-top: 1rem">
            To add a module or another green, contact Bowls Buddy: {{ config('modules.contact') }}.
        </p>
    </x-filament::section>

    <x-filament::section heading="Install a licence key">
        <form wire:submit="install">
            {{ $this->form }}

            <x-filament::button type="submit" style="margin-top: 1rem">
                Install licence
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
