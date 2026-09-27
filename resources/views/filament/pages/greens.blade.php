<x-filament-panels::page>
    <div class="fi-section-content-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <table class="fi-ta-table w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th class="px-4 py-3 text-start font-semibold">Green</th>
                    <th class="px-4 py-3 text-start font-semibold">Rinks</th>
                    <th class="px-4 py-3 text-start font-semibold">Visible to members</th>
                    <th class="px-4 py-3 text-start font-semibold">Bookings</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->greens() as $green => $row)
                    <tr class="border-b border-gray-100 last:border-0 dark:border-white/5">
                        <td class="px-4 py-3 font-semibold">Green {{ $green }}</td>
                        <td class="px-4 py-3">{{ $row['rinks'] }}</td>
                        <td class="px-4 py-3">{{ $row['hidden'] ? 'Hidden' : 'Yes' }}</td>
                        <td class="px-4 py-3">{{ $row['bookings'] }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-3" colspan="4">No greens yet - add one above.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-sm text-gray-500 dark:text-gray-400">
        Playing times and capacity for individual rinks are edited on the Rinks page. Deleting is
        only possible while a green has no bookings; hide it instead to keep the club's history.
    </p>
</x-filament-panels::page>
