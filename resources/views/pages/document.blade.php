@extends('layouts.app', ['title' => \App\Support\StandardTexts::DOCUMENTS[$document], 'narrow' => true])

@section('content')
    <div class="page-header">
        <h1>{{ \App\Support\StandardTexts::DOCUMENTS[$document] }}</h1>
    </div>

    <div class="card">
        <div class="prose">{!! \App\Support\StandardTexts::for($document) !!}</div>

        @if ($document === 'info')
            <p class="links">
                <a href="{{ route('terms') }}">Business Terms</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
            </p>
        @endif
    </div>
@endsection
