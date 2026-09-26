@extends('layouts.app', ['title' => 'Register', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Register</h1>

        <form method="POST" action="{{ route('register') }}">
            @csrf
            <input type="hidden" name="opened_at" value="{{ $openedAt }}">
            <div style="position: absolute; left: -9999px;" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
            </div>

            <label for="firstname">First name</label>
            <input id="firstname" type="text" name="firstname" value="{{ old('firstname') }}" required autofocus>
            @error('firstname') <div class="error">{{ $message }}</div> @enderror

            <label for="lastname">Surname</label>
            <input id="lastname" type="text" name="lastname" value="{{ old('lastname') }}" required>
            @error('lastname') <div class="error">{{ $message }}</div> @enderror

            <label for="email">Email address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            @error('email') <div class="error">{{ $message }}</div> @enderror

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <div class="error">{{ $message }}</div> @enderror

            <label for="password_confirmation">Password again</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

            <label class="check">
                <input type="checkbox" name="accept_terms" value="1" @checked(old('accept_terms'))>
                <span>I accept the <a href="{{ route('documents.show', 'terms') }}" target="_blank">Business Terms</a>
                and the <a href="{{ route('documents.show', 'privacy') }}" target="_blank">Privacy Policy</a>.</span>
            </label>
            @error('accept_terms') <div class="error">{{ $message }}</div> @enderror

            <button type="submit" class="full">Create my account</button>
        </form>

        <p class="muted">Already have an account? <a href="{{ route('login') }}">Log in</a>.</p>
    </div>
@endsection
