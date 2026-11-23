@extends('layouts.material')

@section('title', 'Shared Academy material')
@section('description', 'A shared learning document from the ZENTRIX ASTRA Cybersecurity Academy.')

@section('content')
    <main class="academy-shared-material">
        <header class="academy-shared-header">
            <a class="button button-outline button-small" href="{{ route('home') }}">ZENTRIX ASTRA</a>
            <div class="academy-material-downloads">
                <a class="button button-outline button-small" href="{{ $htmlDownloadUrl }}">Download HTML</a>
                <a class="button button-primary button-small" href="{{ $pdfDownloadUrl }}">Download PDF</a>
            </div>
        </header>
        <article class="academy-material-paper">
            @include('academy.partials.material-content', [
                'content' => $material->content,
                'level' => $material->level,
            ])
        </article>
        <p class="academy-shared-disclaimer">Shared learning material. Verify technical guidance against current trusted sources before using it.</p>
    </main>
@endsection
