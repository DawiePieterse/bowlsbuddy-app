<x-filament-panels::page>
    @php($pending = $this->pendingMigrations())

    <x-filament::section heading="Database updates">
        @if ($pending)
            <p>{{ count($pending) }} update(s) waiting to be applied:</p>
            <ul class="list-disc ps-6">
                @foreach ($pending as $migration)
                    <li><code>{{ $migration }}</code></li>
                @endforeach
            </ul>
        @else
            <p>The database is up to date.</p>
        @endif
    </x-filament::section>
</x-filament-panels::page>
