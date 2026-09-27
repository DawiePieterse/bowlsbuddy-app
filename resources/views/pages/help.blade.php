@extends('layouts.app', ['title' => 'Help', 'narrow' => true])

@section('content')
    <div class="card">
        <h1>Help</h1>
        {!! \App\Support\StandardTexts::for('help') !!}
        @if (\App\Support\ClubDocuments::exists('help'))
            <a class="button subtle" href="{{ route('documents.show', 'help') }}" target="_blank">Open the help guide (PDF)</a>
        @endif
    </div>
@endsection
