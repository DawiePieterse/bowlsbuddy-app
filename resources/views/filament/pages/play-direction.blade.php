<x-filament-panels::page>
    @php($days = $this->upcoming())
    @php($greens = array_keys(reset($days) ?: []))

    <x-filament::section heading="Next two weeks">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-start">
                    <th class="py-2 text-start">Day</th>
                    @foreach ($greens as $green)
                        <th class="py-2 text-start">Green {{ $green }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($days as $date => $directions)
                    <tr class="border-t border-gray-200 dark:border-white/10">
                        <td class="py-2">{{ \Illuminate\Support\Carbon::parse($date)->format('D j M') }}</td>
                        @foreach ($directions as $direction)
                            <td class="py-2 {{ $direction ? 'font-medium' : 'text-gray-500' }}">{{ \App\Support\GreenDirections::label($direction) }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
