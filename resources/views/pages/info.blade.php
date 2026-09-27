@extends('layouts.app', ['title' => 'Info', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Info</h1>
        @if ($text = app(\App\Support\Settings::class)->get('service.info'))
            {!! $text !!}
        @elseif (! \App\Support\ClubDocuments::exists('info'))
            <p class="muted">The Club Secretary has not written this page yet.</p>
        @endif
        @if (\App\Support\ClubDocuments::exists('info'))
            <a class="button subtle" href="{{ route('documents.show', 'info') }}" target="_blank">Open the info sheet (PDF)</a>
        @endif
        @php($legal = array_filter(['terms', 'privacy'], fn ($document) => \App\Support\ClubDocuments::exists($document)))
        @if ($legal)
            <p class="muted" style="margin-top: 16px;">
                @foreach ($legal as $document)
                    <a href="{{ route('documents.show', $document) }}" target="_blank">{{ \App\Support\ClubDocuments::ALL[$document] }}</a>@if (! $loop->last) &middot; @endif
                @endforeach
            </p>
        @endif
    </div>
@endsection
