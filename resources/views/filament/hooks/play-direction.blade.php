{{-- Today's direction of play, at the top of every admin page. --}}
@php($directions = app(\App\Support\GreenDirections::class)->forDay(today()))

@if ($directions)
    <div class="flex flex-wrap gap-x-6 gap-y-1 rounded-lg bg-primary-50 px-4 py-2 text-sm dark:bg-white/5">
        <span class="text-gray-500 dark:text-gray-400">Direction of play today</span>
        @foreach ($directions as $green => $direction)
            <span>Green {{ $green }}: <strong>{{ \App\Support\GreenDirections::label($direction) }}</strong></span>
        @endforeach
    </div>
@endif
