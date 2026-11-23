@extends('layouts.app')

@section('title', $lesson->title)

@section('workspace')
    <div class="workspace-content academy-content">
        <nav class="academy-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('academy.index') }}">Academy</a>
            <span aria-hidden="true">/</span>
            <a href="{{ route('academy.courses.show', $course->slug) }}">{{ $course->title }}</a>
        </nav>

        <header class="workspace-heading">
            <div>
                <p class="eyebrow">LESSON</p>
                <h1>{{ $lesson->title }}</h1>
                @if ($lesson->summary)
                    <p class="workspace-intro">{{ $lesson->summary }}</p>
                @endif
            </div>
            <x-status-badge :tone="$progress->completed_at ? 'good' : 'muted'">
                {{ $progress->completed_at ? 'Complete' : 'In progress' }}
            </x-status-badge>
        </header>

        @if (session('status'))
            <div class="form-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <x-panel class="academy-lesson-content">
            <p class="eyebrow">LEARNING MATERIAL</p>
            <div class="academy-prose">{{ $lesson->content }}</div>
        </x-panel>

        @if ($quiz && $quizQuestions->isNotEmpty())
            <section class="academy-quiz-section" aria-labelledby="academy-quiz-title">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">CHECK YOUR UNDERSTANDING</p>
                        <h2 id="academy-quiz-title">{{ $quiz->title }}</h2>
                        <p class="workspace-intro">Score at least {{ $quiz->pass_percentage }}% to complete this lesson. Your answers are checked on the server.</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('academy.quizzes.attempts.store', $quiz) }}" class="academy-quiz-form">
                    @csrf
                    @foreach ($quizQuestions as $index => $question)
                        <fieldset class="academy-question">
                            <legend>{{ $index + 1 }}. {{ $question['prompt'] }}</legend>
                            @foreach ($question['options'] as $key => $option)
                                <label class="academy-option">
                                    <input type="radio" name="answers[{{ $question['id'] }}]" value="{{ $key }}"
                                        @checked(old('answers.'.$question['id']) === $key) required>
                                    <span>{{ $option }}</span>
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                    <button class="button button-primary button-small" type="submit">Submit knowledge check</button>
                </form>

                @if ($recentAttempts->isNotEmpty())
                    <div class="academy-recent-attempts">
                        <h3>Recent attempts</h3>
                        @foreach ($recentAttempts as $attempt)
                            <a href="{{ route('academy.attempts.show', $attempt) }}">
                                <span>{{ $attempt->submitted_at->format('M j, Y H:i') }}</span>
                                <x-status-badge :tone="$attempt->passed ? 'good' : 'warning'">
                                    {{ $attempt->score }}% · {{ $attempt->passed ? 'Passed' : 'Try again' }}
                                </x-status-badge>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @elseif ($quiz)
            <x-panel class="academy-completion-panel">
                <div>
                    <p class="eyebrow">ASSESSMENT UNAVAILABLE</p>
                    <h2>This lesson is not ready to complete</h2>
                    <p>The published knowledge check has no available questions. Please return later.</p>
                </div>
            </x-panel>
        @elseif (! $progress->completed_at)
            <x-panel class="academy-completion-panel">
                <div>
                    <p class="eyebrow">LESSON PROGRESS</p>
                    <h2>Ready to mark this lesson complete?</h2>
                    <p>Review the material above. This lesson has no published knowledge check.</p>
                </div>
                <form method="POST" action="{{ route('academy.lessons.complete', [$course->slug, $lesson->slug]) }}">
                    @csrf
                    <button class="button button-primary button-small" type="submit">Mark lesson complete</button>
                </form>
            </x-panel>
        @else
            <x-panel class="academy-completion-panel">
                <div>
                    <p class="eyebrow">LESSON PROGRESS</p>
                    <h2>Lesson complete</h2>
                    <p>Completed {{ $progress->completed_at->format('M j, Y') }}.</p>
                </div>
            </x-panel>
        @endif

        <nav class="academy-lesson-navigation" aria-label="Lesson navigation">
            <a class="button button-outline button-small" href="{{ route('academy.courses.show', $course->slug) }}">Back to course</a>
            @if ($previousLesson)
                <a class="button button-quiet button-small" href="{{ route('academy.lessons.show', [$course->slug, $previousLesson->slug]) }}">Previous: {{ $previousLesson->title }}</a>
            @endif
            @if ($nextLesson)
                <a class="button button-primary button-small" href="{{ route('academy.lessons.show', [$course->slug, $nextLesson->slug]) }}">Next lesson <span aria-hidden="true">→</span></a>
            @endif
        </nav>
    </div>
@endsection
