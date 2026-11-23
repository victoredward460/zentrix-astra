@extends('layouts.app')

@section('title', $course->title)

@section('workspace')
    <div class="workspace-content academy-content">
        <nav class="academy-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('academy.index') }}">Academy</a>
            <span aria-hidden="true">/</span>
            <span>{{ $course->category->name }}</span>
        </nav>

        <header class="workspace-heading">
            <div>
                <p class="eyebrow">{{ strtoupper($course->level) }} COURSE</p>
                <h1>{{ $course->title }}</h1>
                <p class="workspace-intro">{{ $course->summary }}</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('academy.history') }}">Learning history</a>
                <a class="button button-outline button-small" href="{{ route('academy.courses.download', $course->slug) }}">Download course PDF</a>
                <x-status-badge tone="muted">{{ $completedCount }} / {{ $course->lessons->count() }} complete</x-status-badge>
            </div>
        </header>

        <x-panel class="academy-course-overview">
            <div class="academy-course-progress">
                <span>Course progress</span>
                <div class="academy-progress-track" role="progressbar"
                    aria-label="Course progress"
                    aria-valuemin="0"
                    aria-valuemax="{{ max(1, $course->lessons->count()) }}"
                    aria-valuenow="{{ $completedCount }}">
                    <span style="width: {{ $course->lessons->isEmpty() ? 0 : (int) round(($completedCount / $course->lessons->count()) * 100) }}%"></span>
                </div>
            </div>
            <p>{{ $course->estimated_minutes }} minutes · {{ $course->lessons->count() }} published {{ str('lesson')->plural($course->lessons->count()) }}</p>
        </x-panel>

        @if ($course->lessons->isEmpty())
            <x-empty-state title="Lessons are being prepared" description="Published lessons for this course will appear here." />
        @else
            <section class="academy-lesson-list" aria-labelledby="academy-lessons-heading">
                <div class="dashboard-section-heading">
                    <div>
                        <p class="eyebrow">COURSE CONTENT</p>
                        <h2 id="academy-lessons-heading">Lessons</h2>
                    </div>
                </div>
                <div class="academy-lesson-rows">
                    @foreach ($course->lessons as $index => $lesson)
                        @php($completed = $completedLessonIds->contains($lesson->id))
                        <a class="academy-lesson-row" href="{{ route('academy.lessons.show', [$course->slug, $lesson->slug]) }}">
                            <span class="academy-lesson-number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <span class="academy-lesson-copy">
                                <strong>{{ $lesson->title }}</strong>
                                <small>{{ $lesson->summary ?: 'Read the lesson and review the key ideas.' }}</small>
                            </span>
                            @if ($lesson->quiz?->is_published)
                                <span class="academy-lesson-type">Knowledge check</span>
                            @endif
                            <x-status-badge :tone="$completed ? 'good' : 'muted'">{{ $completed ? 'Complete' : 'Not complete' }}</x-status-badge>
                            <span class="academy-lesson-arrow" aria-hidden="true">→</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
