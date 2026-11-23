@extends('layouts.app')

@section('title', 'Learning history')

@section('workspace')
    <div class="workspace-content academy-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> LEARNING HISTORY</p>
                <h1>Your learning history</h1>
                <p class="workspace-intro">Lesson activity and knowledge-check attempts saved to your account.</p>
            </div>
            <a class="button button-outline button-small" href="{{ route('academy.index') }}">Browse courses</a>
        </header>

        <section class="academy-history-section" aria-labelledby="lesson-progress-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">LESSON ACTIVITY</p><h2 id="lesson-progress-heading">Started and completed lessons</h2></div>
            </div>
            @if ($progress->isEmpty())
                <x-empty-state title="No lessons started yet" description="Open a published course to begin. Your lesson progress will appear here." />
            @else
                <div class="academy-history-list">
                    @foreach ($progress as $item)
                        <a href="{{ route('academy.lessons.show', [$item->lesson->course->slug, $item->lesson->slug]) }}">
                            <span><strong>{{ $item->lesson->title }}</strong><small>{{ $item->lesson->course->title }} · Last viewed {{ $item->last_viewed_at?->format('M j, Y') ?? 'Not recorded' }}</small></span>
                            <x-status-badge :tone="$item->completed_at ? 'good' : 'muted'">{{ $item->completed_at ? 'Complete' : 'In progress' }}</x-status-badge>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $progress->links() }}</div>
            @endif
        </section>

        <section class="academy-history-section" aria-labelledby="quiz-history-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">ASSESSMENTS</p><h2 id="quiz-history-heading">Knowledge-check attempts</h2></div>
            </div>
            @if ($attempts->isEmpty())
                <x-empty-state title="No quiz attempts yet" description="Knowledge checks are available within published lessons that include an assessment." />
            @else
                <div class="academy-history-list">
                    @foreach ($attempts as $attempt)
                        <a href="{{ route('academy.attempts.show', $attempt) }}">
                            <span><strong>{{ $attempt->quiz->title }}</strong><small>{{ $attempt->quiz->lesson->course->title }} · {{ $attempt->submitted_at->format('M j, Y H:i') }}</small></span>
                            <x-status-badge :tone="$attempt->passed ? 'good' : 'warning'">{{ $attempt->score }}% · {{ $attempt->passed ? 'Passed' : 'Not passed' }}</x-status-badge>
                        </a>
                    @endforeach
                </div>
                <div class="scanner-pagination">{{ $attempts->links() }}</div>
            @endif
        </section>
    </div>
@endsection
