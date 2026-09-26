@extends('layouts.app', ['title' => 'My account', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>My account</h1>
        <p>{{ trim(auth()->user()->firstName().' '.auth()->user()->lastName()) ?: auth()->user()->alias }}
            <span class="muted">&middot; {{ auth()->user()->email }}</span></p>
    </div>

    <div class="card">
        <h2>Change email address</h2>
        <form method="POST" action="{{ route('account.email') }}">
            @csrf
            @method('PUT')

            <label for="email">New email address</label>
            <input id="email" type="email" name="email" value="{{ old('email', auth()->user()->email) }}" required>
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="current_password_email">Current password</label>
            <input id="current_password_email" type="password" name="current_password" required autocomplete="current-password">
            @error('current_password') <div class="error">{{ $message }}</div> @enderror

            <button type="submit">Change email</button>
        </form>
    </div>

    <div class="card">
        <h2>Change password</h2>
        <form method="POST" action="{{ route('account.password') }}">
            @csrf
            @method('PUT')

            <label for="password">New password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <label for="password_confirmation">New password again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

            <label for="current_password_pw">Current password</label>
            <input id="current_password_pw" type="password" name="current_password" required autocomplete="current-password">
            @error('current_password') <div class="error">{{ $message }}</div> @enderror

            <button type="submit">Change password</button>
        </form>
    </div>

    <div class="card">
        <h2>My data</h2>
        <p class="muted">Download everything we store about you as a file.</p>
        <a class="button subtle" href="{{ route('account.data') }}">Download my data</a>
    </div>

    <div class="card">
        <h2>Delete my account</h2>
        <p class="muted">This removes your account and your bookings for good.</p>
        <form method="POST" action="{{ route('account.destroy') }}"
              onsubmit="return confirm('Delete your account and all your bookings? This cannot be undone.');">
            @csrf
            @method('DELETE')

            <label for="current_password_delete">Current password</label>
            <input id="current_password_delete" type="password" name="current_password" required autocomplete="current-password">
            @error('current_password') <div class="error">{{ $message }}</div> @enderror

            <button type="submit" class="danger">Delete my account</button>
        </form>
    </div>
@endsection
