@extends('layouts.app')

@section('title', 'Knowledge check result')

@section('workspace')
    <div class="workspace-content academy-content">
        <nav class="academy-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('academy.index') }}">Academy</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('academy.courses.show', $attempt->quiz->lesson->course->slug) }}">{{ $attempt->quiz->lesson->course->title }}</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('academy.lessons.show', [$attempt->quiz->lesson->course->slug, $attempt->quiz->lesson->slug]) }}">{{ $attempt->quiz->lesson->title }}</a>
        </nav>

        <header class="workspace-heading">
            <div>
                <p class="eyebrow">KNOWLEDGE CHECK RESULT</p>
                <h1>{{ $attempt->score }}% — {{ $attempt->passed ? 'Passed' : 'Keep learning' }}</h1>
                <p class="workspace-intro">Submitted {{ $attempt->submitted_at->format('M j, Y H:i') }}. Feedback below is based on the saved attempt.</p>
            </div>
            <x-status-badge :tone="$attempt->passed ? 'good' : 'warning'">{{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-status-badge>
        </header>

        <div class="academy-result-list">
            @foreach ($attempt->results as $index => $result)
                <x-panel class="academy-result-card">
                    <div class="academy-result-heading">
                        <h2>{{ $index + 1 }}. {{ $result['prompt'] }}</h2>
                        <x-status-badge :tone="$result['is_correct'] ? 'good' : 'warning'">
                            {{ $result['is_correct'] ? 'Correct' : 'Review' }}
                        </x-status-badge>
                    </div>
                    <p><strong>Your answer:</strong> {{ $result['options'][$result['selected_option']] ?? 'No answer recorded' }}</p>
                    @unless ($result['is_correct'])
                        <p><strong>Correct answer:</strong> {{ $result['options'][$result['correct_option']] ?? 'Unavailable' }}</p>
                    @endunless
                    <p>{{ $result['explanation'] }}</p>
                </x-panel>
            @endforeach
        </div>

        <div class="academy-lesson-navigation">
            <a class="button button-outline button-small" href="{{ route('academy.lessons.show', [$attempt->quiz->lesson->course->slug, $attempt->quiz->lesson->slug]) }}">Return to lesson</a>
            <a class="button button-quiet button-small" href="{{ route('academy.history') }}">Learning history</a>
        </div>
    </div>
@endsection
