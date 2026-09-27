@extends('layouts.app', ['title' => \App\Support\StandardTexts::DOCUMENTS[$document], 'narrow' => true])

@section('content')
    <div class="card">
        <h1>{{ \App\Support\StandardTexts::DOCUMENTS[$document] }}</h1>
        {!! \App\Support\StandardTexts::for($document) !!}

        @if ($document === 'info')
            <p class="muted" style="margin-top: 16px;">
                <a href="{{ route('terms') }}">Business Terms</a> &middot;
                <a href="{{ route('privacy') }}">Privacy Policy</a>
            </p>
        @endif
    </div>
@endsection
