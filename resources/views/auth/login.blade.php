@extends('layouts.app', ['title' => 'Log in', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Log in</h1>

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <label class="check"><input type="checkbox" name="remember" value="1"> Keep me logged in</label>

            <button type="submit">Log in</button>
        </form>

        <p class="muted"><a href="{{ route('password.request') }}">Forgot your password?</a></p>
        <p class="muted">New here? <a href="{{ route('register') }}">Register</a>.</p>
    </div>
@endsection
