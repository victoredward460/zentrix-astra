@extends('layouts.app')

@section('title', 'Edit Academy lesson')

@section('workspace')
    <div class="workspace-content academy-content">
        <nav class="academy-breadcrumbs" aria-label="Breadcrumb">
            <a href="{{ route('admin.academy.index') }}">Academy content</a>
            <span aria-hidden="true">/</span>
            <span>Edit lesson</span>
        </nav>
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION <span aria-hidden="true">/</span> LESSON EDITOR</p>
                <h1>{{ $lesson->title }}</h1>
                <p class="workspace-intro">Lesson material is stored as plain text and rendered safely as escaped content.</p>
            </div>
            <a class="button button-outline button-small" href="{{ route('admin.academy.index') }}">Back to Academy content</a>
        </header>

        @if (session('status'))
            <div class="form-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error" role="alert">
                <strong>Review the lesson or knowledge-check form.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <x-panel class="academy-admin-form-panel">
            <h2>Lesson details</h2>
            <form method="POST" action="{{ route('admin.academy.lessons.update', $lesson) }}" class="academy-admin-form">
                @csrf
                @method('PUT')
                <label>Course
                    <select class="auth-input" name="academy_course_id" required>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" @selected($lesson->academy_course_id === $course->id)>{{ $course->category->name }} / {{ $course->title }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Title <input class="auth-input" name="title" value="{{ old('title', $lesson->title) }}" maxlength="160" required></label>
                <label>Slug <input class="auth-input" name="slug" value="{{ old('slug', $lesson->slug) }}" maxlength="180" pattern="[A-Za-z0-9_-]+" required></label>
                <label>Summary <textarea class="auth-input" name="summary" rows="2" maxlength="1000">{{ old('summary', $lesson->summary) }}</textarea></label>
                <label>Lesson content <textarea class="auth-input academy-content-input" name="content" rows="12" maxlength="30000" required>{{ old('content', $lesson->content) }}</textarea></label>
                <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $lesson->sort_order) }}" required></label>
                <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->is_published))> Published to learners</label>
                <button class="button button-primary button-small" type="submit">Save lesson</button>
            </form>
        </x-panel>

        <section class="academy-admin-section" aria-labelledby="academy-quiz-editor-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">ASSESSMENT</p><h2 id="academy-quiz-editor-heading">Knowledge check</h2></div>
            </div>
            <x-panel class="academy-admin-form-panel">
                <form method="POST" action="{{ route('admin.academy.quizzes.save', $lesson) }}" class="academy-admin-form">
                    @csrf
                    <label>Title <input class="auth-input" name="title" value="{{ old('title', $lesson->quiz?->title ?? 'Knowledge check') }}" maxlength="160" required></label>
                    <label>Passing score (%) <input class="auth-input" name="pass_percentage" type="number" min="50" max="100" value="{{ old('pass_percentage', $lesson->quiz?->pass_percentage ?? 70) }}" required></label>
                    <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $lesson->quiz?->is_published ?? false))> Published to learners</label>
                    <p class="form-hint">A knowledge check requires at least one saved question before publication. Learners must pass it to complete the lesson.</p>
                    <button class="button button-primary button-small" type="submit">Save knowledge check</button>
                </form>
            </x-panel>

            @if ($lesson->quiz)
                <div class="academy-admin-questions">
                    <div class="dashboard-section-heading">
                        <div><p class="eyebrow">QUESTION BANK</p><h3>{{ $lesson->quiz->questions->count() }} questions</h3></div>
                        <x-status-badge :tone="$lesson->quiz->is_published ? 'good' : 'muted'">{{ $lesson->quiz->is_published ? 'Published' : 'Draft' }}</x-status-badge>
                    </div>
                    @foreach ($lesson->quiz->questions as $question)
                        <x-panel class="academy-admin-question">
                            <form method="POST" action="{{ route('admin.academy.questions.update', [$lesson->quiz, $question]) }}" class="academy-admin-form">
                                @csrf
                                @method('PUT')
                                <label>Question <textarea class="auth-input" name="prompt" rows="2" maxlength="1000" required>{{ $question->prompt }}</textarea></label>
                                @foreach (['a', 'b', 'c', 'd'] as $optionKey)
                                    <label>Option {{ strtoupper($optionKey) }} <input class="auth-input" name="options[{{ $optionKey }}]" value="{{ $question->options[$optionKey] ?? '' }}" maxlength="300" required></label>
                                @endforeach
                                <label>Correct option
                                    <select class="auth-input" name="correct_option" required>
                                        @foreach (['a', 'b', 'c', 'd'] as $optionKey)
                                            <option value="{{ $optionKey }}" @selected($question->correct_option === $optionKey)>{{ strtoupper($optionKey) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Explanation <textarea class="auth-input" name="explanation" rows="2" maxlength="1500" required>{{ $question->explanation }}</textarea></label>
                                <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ $question->sort_order }}" required></label>
                                <button class="button button-outline button-small" type="submit">Save question</button>
                            </form>
                            @unless ($lesson->quiz->is_published)
                                <form method="POST" action="{{ route('admin.academy.questions.destroy', [$lesson->quiz, $question]) }}" class="academy-question-delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button class="button button-quiet button-small" type="submit">Remove question</button>
                                </form>
                            @endunless
                        </x-panel>
                    @endforeach

                    <x-panel class="academy-admin-form-panel">
                        <h3>Add a question</h3>
                        <form method="POST" action="{{ route('admin.academy.questions.store', $lesson->quiz) }}" class="academy-admin-form">
                            @csrf
                            <label>Question <textarea class="auth-input" name="prompt" rows="2" maxlength="1000" required>{{ old('prompt') }}</textarea></label>
                            @foreach (['a', 'b', 'c', 'd'] as $optionKey)
                                <label>Option {{ strtoupper($optionKey) }} <input class="auth-input" name="options[{{ $optionKey }}]" value="{{ old('options.'.$optionKey) }}" maxlength="300" required></label>
                            @endforeach
                            <label>Correct option
                                <select class="auth-input" name="correct_option" required>
                                    @foreach (['a', 'b', 'c', 'd'] as $optionKey)
                                        <option value="{{ $optionKey }}" @selected(old('correct_option', 'a') === $optionKey)>{{ strtoupper($optionKey) }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>Explanation <textarea class="auth-input" name="explanation" rows="2" maxlength="1500" required>{{ old('explanation') }}</textarea></label>
                            <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', ($lesson->quiz->questions->max('sort_order') ?? 0) + 10) }}" required></label>
                            <button class="button button-primary button-small" type="submit">Add question</button>
                        </form>
                    </x-panel>
                </div>
            @else
                <x-empty-state title="Save the knowledge-check settings first" description="Once the quiz is created, you can add and manage its questions here." />
            @endif
        </section>
    </div>
@endsection
