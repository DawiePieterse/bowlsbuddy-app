@extends('layouts.app', ['title' => 'Set up your club', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Set up your club</h1>
        <p class="muted">A few questions and your booking system is ready. Everything can be changed
            later in the admin panel.</p>

        <form method="POST" action="{{ route('setup.store') }}">
            @csrf

            <label for="name">Club name</label>
            <input id="name" type="text" name="name" value="{{ old('name', $defaults['name']) }}" required>
            @error('name') <div class="error">{{ $message }}</div> @enderror

            <label for="short_name">Short name</label>
            <input id="short_name" type="text" name="short_name" value="{{ old('short_name', $defaults['short_name']) }}" required>
            @error('short_name') <div class="error">{{ $message }}</div> @enderror

            <label for="greens">Greens (comma-separated, e.g. A,B)</label>
            <input id="greens" type="text" name="greens" value="{{ old('greens', implode(',', $defaults['greens'])) }}" required>
            @error('greens') <div class="error">{{ $message }}</div> @enderror

            <label for="rinks_per_green">Rinks per green</label>
            <input id="rinks_per_green" type="text" inputmode="numeric" name="rinks_per_green" value="{{ old('rinks_per_green', $defaults['rinks_per_green']) }}" required>
            @error('rinks_per_green') <div class="error">{{ $message }}</div> @enderror

            <label for="time_start">First slot starts</label>
            <input id="time_start" type="text" name="time_start" value="{{ old('time_start', $defaults['time_start']) }}" placeholder="12:00" required>
            @error('time_start') <div class="error">{{ $message }}</div> @enderror

            <label for="time_end">Last slot ends</label>
            <input id="time_end" type="text" name="time_end" value="{{ old('time_end', $defaults['time_end']) }}" placeholder="17:00" required>
            @error('time_end') <div class="error">{{ $message }}</div> @enderror

            <label for="slot_minutes">Slot length (minutes)</label>
            <input id="slot_minutes" type="text" inputmode="numeric" name="slot_minutes" value="{{ old('slot_minutes', $defaults['slot_minutes']) }}" required>
            @error('slot_minutes') <div class="error">{{ $message }}</div> @enderror

            <label for="players_per_rink">Players per rink</label>
            <input id="players_per_rink" type="text" inputmode="numeric" name="players_per_rink" value="{{ old('players_per_rink', $defaults['players_per_rink']) }}" required>
            @error('players_per_rink') <div class="error">{{ $message }}</div> @enderror

            <label for="booking_range_days">Bookable ahead (days)</label>
            <input id="booking_range_days" type="text" inputmode="numeric" name="booking_range_days" value="{{ old('booking_range_days', $defaults['booking_range_days']) }}" required>
            @error('booking_range_days') <div class="error">{{ $message }}</div> @enderror

            <label for="cancel_range_hours">Cancel cut-off (hours)</label>
            <input id="cancel_range_hours" type="text" inputmode="numeric" name="cancel_range_hours" value="{{ old('cancel_range_hours', $defaults['cancel_range_hours']) }}" required>
            @error('cancel_range_hours') <div class="error">{{ $message }}</div> @enderror

            <label for="admin_email">Secretary email address</label>
            <input id="admin_email" type="email" name="admin_email" value="{{ old('admin_email') }}" required>
            @error('admin_email') <div class="error">{{ $message }}</div> @enderror

            <label for="admin_password">Secretary password</label>
            <input id="admin_password" type="password" name="admin_password" required autocomplete="new-password">
            @error('admin_password') <div class="error">{{ $message }}</div> @enderror

            <button type="submit" class="full">Create my club</button>
        </form>
    </div>
@endsection
