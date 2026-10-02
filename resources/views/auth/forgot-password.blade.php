@extends('layouts.app', ['title' => 'Forgot password', 'narrow' => true])

@section('content')
    <div class="page-header">
        <h1>Forgot your password?</h1>
    </div>

    <div class="card">
        <p style="margin-top: 0;">No problem. Please ask the <strong>Club Secretary</strong> to set a temporary password for
            you, then log in with it and choose a new one under <em>My account</em>.</p>
        <p class="muted">Bowls Buddy sends no email, so there is no reset link &mdash; the Secretary is
            the quickest way back in.</p>
        <a class="button subtle" href="{{ route('login') }}">{{ svg('heroicon-o-chevron-left', 'icon') }}Back to log in</a>
    </div>
@endsection
