@use('App\Filament\Pages\Licence')
@use('App\Support\Licensing\Modules')

{{-- Shown at the top of every panel page once the licence has expired (docs/MODULES.md section 5). --}}
@php
    $modules = app(Modules::class);
    $licence = $modules->licence();
@endphp

@if ($licence !== null && $licence->expires->lt(today()))
    @php($readOnly = today()->gt($modules->graceEnds($licence)))
    <style>
        .bb-licence-banner { margin: 1rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; font-size: 0.875rem;
            background-color: rgb(254 243 199); color: rgb(120 53 15); }
        .bb-licence-banner--read-only { background-color: rgb(254 226 226); color: rgb(127 29 29); }
        .dark .bb-licence-banner { background-color: rgb(120 53 15 / 0.35); color: rgb(254 243 199); }
        .dark .bb-licence-banner--read-only { background-color: rgb(127 29 29 / 0.4); color: rgb(254 226 226); }
        .bb-licence-banner a { text-decoration: underline; }
    </style>
    <div role="status" @class(['bb-licence-banner', 'bb-licence-banner--read-only' => $readOnly])>
        The Bowls Buddy licence expired on {{ $licence->expires->format('j F Y') }}.
        @if ($readOnly)
            The club's extra modules are read-only until it is renewed. Bookings carry on as normal.
        @else
            Please renew by {{ $modules->graceEnds($licence)->format('j F Y') }} to keep the club's extra modules working.
        @endif
        @if (Licence::canAccess())
            <a href="{{ Licence::getUrl() }}">Install a new licence</a>
        @endif
    </div>
@endif
