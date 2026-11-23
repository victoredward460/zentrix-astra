@extends('layouts.app')

@section('title', 'Academy content management')

@section('workspace')
    <div class="workspace-content academy-content">
        <header class="workspace-heading">
            <div>
                <p class="eyebrow">ADMINISTRATION <span aria-hidden="true">/</span> CONTENT</p>
                <h1>Academy content</h1>
                <p class="workspace-intro">Create and maintain categories, courses, lessons, and knowledge checks. Unpublished material is hidden from learners.</p>
            </div>
            <a class="button button-outline button-small" href="{{ route('academy.index') }}">Preview Academy</a>
        </header>

        @if (session('status'))
            <div class="form-notice" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="form-error" role="alert">
                <strong>Review the content form.</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <x-panel class="academy-admin-form-panel">
            <p class="eyebrow">OPENROUTER · ADMIN ONLY</p>
            <h2>Generate a course draft</h2>
            <p>AI uses {{ $aiModels[$aiModel] ?? $aiModel }} and sends the topic and level to OpenRouter and NVIDIA. The provider may retain and use prompts for model training under its policies; strict zero-data-retention eligibility is not confirmed. AI generates a draft only; review lessons, quizzes, and video links before publishing. <a href="{{ route('profile.edit') }}">Manage AI preferences</a>.</p>
            @unless ($aiProviderConsent)
                <p class="form-hint">AI is off for this account. Enable it once in <a href="{{ route('profile.edit') }}">your profile</a> before generating drafts.</p>
            @endunless
            <form method="POST" action="{{ route('admin.academy.generate') }}" class="academy-admin-form">
                @csrf
                <label>Topic <input class="auth-input" name="topic" value="{{ old('topic') }}" maxlength="160" required placeholder="Phishing awareness"></label>
                <label>Level
                    <select class="auth-input" name="level" required>
                        @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                            <option value="{{ $level }}" @selected(old('level', 'beginner') === $level)>{{ ucfirst($level) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary button-small" type="submit" @disabled(! $aiProviderConsent)>Generate draft with OpenRouter</button>
            </form>
        </x-panel>

        <x-panel class="academy-admin-form-panel">
            <p class="eyebrow">DOWNLOADABLE LEARNING MATERIAL</p>
            <h2>Generate an HTML and PDF study guide</h2>
            <p>AI uses {{ $aiModels[$aiModel] ?? $aiModel }} to create structured, defensive learning material. Both files are stored privately. A signed share link is created so anyone with that link can view the guide; delete the material to revoke access. Do not include secrets or private information in the topic.</p>
            <form method="POST" action="{{ route('admin.academy.materials.generate') }}" class="academy-admin-form">
                @csrf
                <label>Topic <input class="auth-input" name="topic" value="{{ old('topic') }}" maxlength="160" required placeholder="Secure account recovery"></label>
                <label>Level
                    <select class="auth-input" name="level" required>
                        @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                            <option value="{{ $level }}" @selected(old('level', 'beginner') === $level)>{{ ucfirst($level) }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="button button-primary button-small" type="submit" @disabled(! $aiProviderConsent)>Generate HTML + PDF and publish link</button>
            </form>
            @if (session('material-share-url'))
                <div class="academy-share-notice" role="status">
                    <strong>Published share link</strong>
                    <a href="{{ session('material-share-url') }}" target="_blank" rel="noopener noreferrer">Open the learning material</a>
                    <input class="auth-input" type="url" readonly value="{{ session('material-share-url') }}" aria-label="Copyable share URL">
                </div>
            @endif
        </x-panel>

        @if ($materials->isNotEmpty())
            <section class="academy-admin-section" aria-labelledby="academy-materials-heading">
                <div class="dashboard-section-heading">
                    <div><p class="eyebrow">PRIVATE FILES · SIGNED SHARING</p><h2 id="academy-materials-heading">Generated learning materials</h2></div>
                </div>
                @foreach ($materials as $material)
                    @php($shareUrl = \Illuminate\Support\Facades\URL::signedRoute('academy.materials.shared', ['material' => $material->public_id]))
                    <x-panel class="academy-admin-form-panel">
                        <div class="academy-admin-title-row">
                            <div>
                                <h3>{{ $material->title }}</h3>
                                <p class="form-hint">{{ ucfirst($material->level) }} · Created {{ $material->created_at->format('M j, Y') }}</p>
                            </div>
                            <x-status-badge tone="good">Published link</x-status-badge>
                        </div>
                        <div class="academy-material-actions">
                            <a class="button button-outline button-small" href="{{ route('admin.academy.materials.download', [$material, 'html']) }}">Download HTML</a>
                            <a class="button button-outline button-small" href="{{ route('admin.academy.materials.download', [$material, 'pdf']) }}">Download PDF</a>
                            <a class="button button-primary button-small" href="{{ $shareUrl }}" target="_blank" rel="noopener noreferrer">Open share link</a>
                        </div>
                        <label class="auth-field">Share URL
                            <input class="auth-input" type="url" readonly value="{{ $shareUrl }}">
                        </label>
                        <form method="POST" action="{{ route('admin.academy.materials.destroy', $material) }}" onsubmit="return confirm('Delete this material and revoke its share link?')">
                            @csrf
                            @method('DELETE')
                            <button class="button button-outline button-small" type="submit">Delete and revoke link</button>
                        </form>
                    </x-panel>
                @endforeach
            </section>
        @endif

        <section class="academy-admin-section" aria-labelledby="academy-categories-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">CONTENT STRUCTURE</p><h2 id="academy-categories-heading">Learning categories</h2></div>
            </div>
            <x-panel class="academy-admin-form-panel">
                <h3>Create a category</h3>
                <form method="POST" action="{{ route('admin.academy.categories.store') }}" class="academy-admin-form">
                    @csrf
                    <label>Name <input class="auth-input" name="name" value="{{ old('name') }}" maxlength="100" required></label>
                    <label>Slug <input class="auth-input" name="slug" value="{{ old('slug') }}" maxlength="120" pattern="[A-Za-z0-9_-]+" required></label>
                    <label>Description <textarea class="auth-input" name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea></label>
                    <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', 0) }}" required></label>
                    <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published'))> Published to learners</label>
                    <button class="button button-primary button-small" type="submit">Create category</button>
                </form>
            </x-panel>

            @foreach ($categories as $category)
                <x-panel class="academy-admin-form-panel">
                    <div class="academy-admin-title-row">
                        <h3>{{ $category->name }}</h3>
                        <x-status-badge :tone="$category->is_published ? 'good' : 'muted'">{{ $category->is_published ? 'Published' : 'Draft' }}</x-status-badge>
                    </div>
                    <form method="POST" action="{{ route('admin.academy.categories.update', $category) }}" class="academy-admin-form">
                        @csrf
                        @method('PUT')
                        <label>Name <input class="auth-input" name="name" value="{{ $category->name }}" maxlength="100" required></label>
                        <label>Slug <input class="auth-input" name="slug" value="{{ $category->slug }}" maxlength="120" pattern="[A-Za-z0-9_-]+" required></label>
                        <label>Description <textarea class="auth-input" name="description" rows="2" maxlength="1000">{{ $category->description }}</textarea></label>
                        <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ $category->sort_order }}" required></label>
                        <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked($category->is_published)> Published to learners</label>
                        <span class="form-hint">{{ $category->courses_count }} {{ str('course')->plural($category->courses_count) }}</span>
                        <button class="button button-outline button-small" type="submit">Save category</button>
                    </form>
                </x-panel>
            @endforeach
        </section>

        <section class="academy-admin-section" aria-labelledby="academy-courses-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">LEARNING PATHS</p><h2 id="academy-courses-heading">Courses</h2></div>
            </div>
            <x-panel class="academy-admin-form-panel">
                <h3>Create a course</h3>
                <form method="POST" action="{{ route('admin.academy.courses.store') }}" class="academy-admin-form">
                    @csrf
                    <label>Category
                        <select class="auth-input" name="academy_category_id" required>
                            <option value="">Choose a category</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(old('academy_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Title <input class="auth-input" name="title" value="{{ old('title') }}" maxlength="160" required></label>
                    <label>Slug <input class="auth-input" name="slug" value="{{ old('slug') }}" maxlength="180" pattern="[A-Za-z0-9_-]+" required></label>
                    <label>Summary <textarea class="auth-input" name="summary" rows="2" maxlength="1500" required>{{ old('summary') }}</textarea></label>
                    <label>Level
                        <select class="auth-input" name="level" required>
                            @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                                <option value="{{ $level }}" @selected(old('level', 'beginner') === $level)>{{ ucfirst($level) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Estimated minutes <input class="auth-input" name="estimated_minutes" type="number" min="1" max="65535" value="{{ old('estimated_minutes', 10) }}" required></label>
                    <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', 0) }}" required></label>
                    <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published'))> Published to learners</label>
                    <button class="button button-primary button-small" type="submit">Create course</button>
                </form>
            </x-panel>

            @foreach ($courses as $course)
                <x-panel class="academy-admin-form-panel">
                    <div class="academy-admin-title-row">
                        <h3>{{ $course->title }}</h3>
                        <x-status-badge :tone="$course->is_published ? 'good' : 'muted'">{{ $course->is_published ? 'Published' : 'Draft' }}</x-status-badge>
                    </div>
                    <form method="POST" action="{{ route('admin.academy.courses.update', $course) }}" class="academy-admin-form">
                        @csrf
                        @method('PUT')
                        <label>Category
                            <select class="auth-input" name="academy_category_id" required>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected($course->academy_category_id === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Title <input class="auth-input" name="title" value="{{ $course->title }}" maxlength="160" required></label>
                        <label>Slug <input class="auth-input" name="slug" value="{{ $course->slug }}" maxlength="180" pattern="[A-Za-z0-9_-]+" required></label>
                        <label>Summary <textarea class="auth-input" name="summary" rows="2" maxlength="1500" required>{{ $course->summary }}</textarea></label>
                        <label>Level
                            <select class="auth-input" name="level" required>
                                @foreach (['beginner', 'intermediate', 'advanced'] as $level)
                                    <option value="{{ $level }}" @selected($course->level === $level)>{{ ucfirst($level) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Estimated minutes <input class="auth-input" name="estimated_minutes" type="number" min="1" max="65535" value="{{ $course->estimated_minutes }}" required></label>
                        <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ $course->sort_order }}" required></label>
                        <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked($course->is_published)> Published to learners</label>
                        <span class="form-hint">{{ $course->lessons_count }} {{ str('lesson')->plural($course->lessons_count) }}</span>
                        <button class="button button-outline button-small" type="submit">Save course</button>
                    </form>
                </x-panel>
            @endforeach
        </section>

        <section class="academy-admin-section" aria-labelledby="academy-lessons-heading">
            <div class="dashboard-section-heading">
                <div><p class="eyebrow">LESSON MATERIAL</p><h2 id="academy-lessons-heading">Lessons</h2></div>
            </div>
            <x-panel class="academy-admin-form-panel">
                <h3>Create a lesson</h3>
                @if ($courses->isEmpty())
                    <x-empty-state title="Create a course first" description="Lessons are organized inside courses." />
                @else
                    <form method="POST" action="{{ route('admin.academy.lessons.store') }}" class="academy-admin-form">
                        @csrf
                        <label>Course
                            <select class="auth-input" name="academy_course_id" required>
                                <option value="">Choose a course</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('academy_course_id') == $course->id)>{{ $course->category->name }} / {{ $course->title }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Title <input class="auth-input" name="title" value="{{ old('title') }}" maxlength="160" required></label>
                        <label>Slug <input class="auth-input" name="slug" value="{{ old('slug') }}" maxlength="180" pattern="[A-Za-z0-9_-]+" required></label>
                        <label>Summary <textarea class="auth-input" name="summary" rows="2" maxlength="1000">{{ old('summary') }}</textarea></label>
                        <label>Lesson content <textarea class="auth-input academy-content-input" name="content" rows="7" maxlength="30000" required>{{ old('content') }}</textarea></label>
                        <label>Order <input class="auth-input" name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', 0) }}" required></label>
                        <label class="academy-checkbox"><input type="checkbox" name="is_published" value="1" @checked(old('is_published'))> Published to learners</label>
                        <p class="form-hint">Use plain text with short sections or bullets. Content is rendered escaped and is not interpreted as HTML.</p>
                        <button class="button button-primary button-small" type="submit">Create lesson</button>
                    </form>
                @endif
            </x-panel>

            @if ($lessons->isEmpty())
                <x-empty-state title="No lessons yet" description="Create the first lesson in a course above." />
            @else
                <div class="academy-admin-lesson-list">
                    @foreach ($lessons as $lesson)
                        <x-panel class="academy-admin-lesson-row">
                            <div>
                                <p class="eyebrow">{{ $lesson->course->title }}</p>
                                <h3>{{ $lesson->title }}</h3>
                            </div>
                            <x-status-badge :tone="$lesson->is_published ? 'good' : 'muted'">{{ $lesson->is_published ? 'Published' : 'Draft' }}</x-status-badge>
                            <a class="button button-outline button-small" href="{{ route('admin.academy.lessons.edit', $lesson) }}">Edit lesson and quiz</a>
                        </x-panel>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
