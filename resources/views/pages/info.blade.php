@extends('layouts.app', ['title' => 'Info', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Info</h1>
        @if ($text = app(\App\Support\Settings::class)->get('service.info'))
            {!! $text !!}
        @else
            <p class="muted">The Club Secretary has not written this page yet.</p>
        @endif
        <p class="muted" style="margin-top: 16px;">
            <a href="{{ route('documents.show', 'terms') }}">Business Terms</a> &middot;
            <a href="{{ route('documents.show', 'privacy') }}">Privacy Policy</a>
        </p>
    </div>
@endsection
