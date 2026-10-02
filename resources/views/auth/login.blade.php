@extends('layouts.app', ['title' => 'Log in', 'narrow' => true])

@section('content')
    <div class="page-header">
        <h1>Log in</h1>
        <p>Book a rink and see who is playing.</p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="login">Cellphone number or email</label>
            <input id="login" type="text" name="login" value="{{ old('login') }}" required autofocus
                   autocomplete="username" inputmode="tel" placeholder="082 123 4567">
            @error('login') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>

            <button type="submit" class="full">Log in</button>
        </form>

        <p class="links">
            <a href="{{ route('password.request') }}">Forgot your password?</a>
            <span class="muted">New here? <a href="{{ route('register') }}">Register</a></span>
        </p>
    </div>
@endsection
