{{-- Direction of play per green for one day. Use on every member page and print: <x-play-direction :date="$day" /> --}}
@props(['date' => today()])

@php($directions = app(\App\Support\GreenDirections::class)->forDay($date))

@if ($directions)
    <div {{ $attributes->class('play-direction') }}>
        <span class="label">Direction of play</span>
        @foreach ($directions as $green => $direction)
            <span class="green">Green {{ $green }}: <strong>{{ \App\Support\GreenDirections::label($direction) }}</strong></span>
        @endforeach
    </div>
@endif
