@props(['direction'])
{{-- Up-down arrow for North-South, left-right arrow for East-West. --}}
<svg class="arrow arrow-{{ $direction === \App\Services\GreenService::DIRECTIONS['NS'] ? 'ns' : 'ew' }}" viewBox="0 0 24 24" aria-hidden="true"
     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 12h18M7 7l-4 5 4 5M17 7l4 5-4 5"/>
</svg>
