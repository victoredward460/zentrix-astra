@extends('layouts.app')

@section('title', 'Cybersecurity Academy')

@section('workspace')
    <div class="workspace-content academy-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">WORKSPACE <span aria-hidden="true">/</span> LEARNING</p>
                <h1>Cybersecurity Academy</h1>
                <p class="workspace-intro">Build practical security habits with short, original lessons and knowledge checks. Your learning progress is private to your account.</p>
            </div>
            <div class="workspace-actions">
                <a class="button button-outline button-small" href="{{ route('academy.history') }}">Learning history</a>
                @if (auth()->user()->isAdministrator())
                    <a class="button button-primary button-small" href="{{ route('admin.academy.index') }}">Manage content</a>
                @endif
            </div>
        </header>

        @if (session('status'))
            <p class="form-notice" role="status">{{ session('status') }}</p>
        @endif
        @include('academy.partials.material-tools')

        @if ($categories->isEmpty())
            <x-empty-state
                title="Courses are being prepared"
                description="Published Academy courses will appear here. Your progress will be saved when you begin a lesson."
            />
        @else
            @foreach ($categories as $category)
                <section class="academy-category" aria-labelledby="academy-category-{{ $category->id }}">
                    <div class="dashboard-section-heading">
                        <div>
                            <p class="eyebrow">LEARNING PATH</p>
                            <h2 id="academy-category-{{ $category->id }}">{{ $category->name }}</h2>
                            @if ($category->description)
                                <p class="workspace-intro">{{ $category->description }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="academy-course-grid">
                        @foreach ($category->courses as $course)
                            @php
                                $courseLessonIds = $completedLessonIds;
                                $completedCount = $course->lessons
                                    ->filter(fn ($lesson) => $courseLessonIds->contains($lesson->id))
                                    ->count();
                            @endphp
                            <x-panel class="academy-course-card">
                                <div class="academy-card-meta">
                                    <x-status-badge tone="accent">{{ ucfirst($course->level) }}</x-status-badge>
                                    <span>{{ $course->estimated_minutes }} min</span>
                                </div>
                                <h3>{{ $course->title }}</h3>
                                <p>{{ $course->summary }}</p>
                                <div class="academy-course-progress">
                                    <span>{{ $completedCount }} of {{ $course->lessons_count }} lessons complete</span>
                                    <div class="academy-progress-track" role="progressbar"
                                        aria-label="Course progress"
                                        aria-valuemin="0"
                                        aria-valuemax="{{ max(1, $course->lessons_count) }}"
                                        aria-valuenow="{{ $completedCount }}">
                                        <span style="width: {{ $course->lessons_count === 0 ? 0 : (int) round(($completedCount / $course->lessons_count) * 100) }}%"></span>
                                    </div>
                                </div>
                                <a class="button button-outline button-small" href="{{ route('academy.courses.show', $course->slug) }}">
                                    Open course <span aria-hidden="true">→</span>
                                </a>
                            </x-panel>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
@endsection
