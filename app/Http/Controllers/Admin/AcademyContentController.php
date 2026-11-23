<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAcademyCategoryRequest;
use App\Http\Requests\Admin\SaveAcademyCourseRequest;
use App\Http\Requests\Admin\SaveAcademyLessonRequest;
use App\Http\Requests\Admin\SaveAcademyQuestionRequest;
use App\Http\Requests\Admin\SaveAcademyQuizRequest;
use App\Models\AcademyCategory;
use App\Models\AcademyCourse;
use App\Models\AcademyLesson;
use App\Models\AcademyQuiz;
use App\Services\Admin\PlatformAuditLogger;
use App\Services\Academy\AcademyCourseGenerator;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AcademyContentController extends Controller
{
    public function __construct(
        private readonly PlatformAuditLogger $auditLogger,
    ) {}

    public function index(): View
    {
        $user = request()->user();

        return view('admin.academy.index', [
            'categories' => AcademyCategory::query()->withCount('courses')->orderBy('sort_order')->orderBy('name')->get(),
            'courses' => AcademyCourse::query()->with('category')->withCount('lessons')->orderBy('academy_category_id')->orderBy('sort_order')->get(),
            'lessons' => AcademyLesson::query()->with('course')->with('quiz')->orderBy('academy_course_id')->orderBy('sort_order')->get(),
            'aiModels' => (array) config('ai.openrouter.models', []),
            'aiModel' => $user->aiModel(),
            'aiProviderConsent' => $user->hasAiProviderConsent(),
            'materials' => $user->academyLearningMaterials()->latest()->limit(30)->get(),
        ]);
    }

    public function generate(Request $request, AcademyCourseGenerator $generator): RedirectResponse
    {
        $validated = $request->validate([
            'topic' => ['required', 'string', 'min:3', 'max:160'],
            'level' => ['required', 'in:beginner,intermediate,advanced'],
        ]);

        if (! $request->user()->hasAiProviderConsent()) {
            return back()->withErrors([
                'ai_generation' => 'Enable AI features in your profile before generating a course draft.',
            ]);
        }

        try {
            $generated = $generator->generate(
                $validated['topic'],
                $validated['level'],
                $request->user()->aiModel(),
            );
        } catch (\App\Exceptions\AgentProviderException $exception) {
            return back()->withErrors([
                'ai_generation' => $exception->userMessage(),
            ]);
        }

        $category = AcademyCategory::query()->firstOrCreate(
            ['slug' => 'ai-generated'],
            [
                'name' => 'AI Generated',
                'description' => 'AI-assisted drafts pending administrator review.',
                'sort_order' => 100,
                'is_published' => false,
            ],
        );

        $courseSlug = Str::slug($generated['title']);
        $suffix = 1;
        while (AcademyCourse::query()->where('slug', $courseSlug)->exists()) {
            $courseSlug = Str::slug($generated['title']).'-'.$suffix++;
        }

        $course = AcademyCourse::query()->create([
            'academy_category_id' => $category->id,
            'title' => mb_substr($generated['title'], 0, 160),
            'slug' => mb_substr($courseSlug, 0, 180),
            'summary' => mb_substr($generated['summary'], 0, 1500),
            'level' => $validated['level'],
            'estimated_minutes' => 30,
            'sort_order' => 100,
            'is_published' => false,
        ]);

        foreach (array_slice($generated['lessons'], 0, 3) as $index => $lessonData) {
            $lessonSlug = Str::slug($lessonData['title']);
            $lesson = AcademyLesson::query()->create([
                'academy_course_id' => $course->id,
                'title' => mb_substr($lessonData['title'], 0, 160),
                'slug' => mb_substr($lessonSlug.'-'.($index + 1), 0, 180),
                'summary' => mb_substr($lessonData['summary'], 0, 1000),
                'content' => mb_substr($lessonData['content'], 0, 30000),
                'sort_order' => $index,
                'is_published' => false,
            ]);

            $quizData = $lessonData['quiz'] ?? [];
            $questions = $quizData['questions'] ?? [];
            if (! is_array($questions) || $questions === []) {
                continue;
            }

            $quiz = $lesson->quiz()->create([
                'title' => mb_substr($quizData['title'] ?? 'Knowledge check', 0, 160),
                'pass_percentage' => 70,
                'is_published' => false,
            ]);

            foreach (array_slice($questions, 0, 4) as $questionIndex => $question) {
                $options = $question['options'] ?? [];
                if (! is_array($options)) {
                    continue;
                }

                $quiz->questions()->create([
                    'prompt' => mb_substr($question['prompt'] ?? '', 0, 1000),
                    'options' => [
                        'a' => mb_substr($options['a'] ?? '', 0, 300),
                        'b' => mb_substr($options['b'] ?? '', 0, 300),
                        'c' => mb_substr($options['c'] ?? '', 0, 300),
                        'd' => mb_substr($options['d'] ?? '', 0, 300),
                    ],
                    'correct_option' => in_array($question['correct_option'] ?? '', ['a','b','c','d'], true)
                        ? $question['correct_option']
                        : 'a',
                    'explanation' => mb_substr($question['explanation'] ?? '', 0, 1500),
                    'sort_order' => $questionIndex,
                ]);
            }
        }

        $this->auditLogger->record($request->user(), 'admin.academy_ai_course_generated', $course, [
            'content_type' => 'course',
            'new_published' => false,
        ]);

        return redirect()
            ->route('admin.academy.lessons.edit', $course->lessons()->first())
            ->with('status', 'AI course draft generated and saved. Review every lesson, quiz, and educational link before publishing.');
    }

    public function storeCategory(SaveAcademyCategoryRequest $request): RedirectResponse
    {
        $category = AcademyCategory::query()->create($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_category_created', $category, [
            'content_type' => 'category',
            'new_published' => $category->is_published,
        ]);

        return back()->with('status', 'Learning category created.');
    }

    public function updateCategory(SaveAcademyCategoryRequest $request, AcademyCategory $category): RedirectResponse
    {
        $wasPublished = $category->is_published;
        $category->update($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_category_updated', $category, [
            'content_type' => 'category',
            'old_published' => $wasPublished,
            'new_published' => $category->is_published,
        ]);

        return back()->with('status', 'Learning category updated.');
    }

    public function storeCourse(SaveAcademyCourseRequest $request): RedirectResponse
    {
        $course = AcademyCourse::query()->create($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_course_created', $course, [
            'content_type' => 'course',
            'new_published' => $course->is_published,
        ]);

        return back()->with('status', 'Course created.');
    }

    public function updateCourse(SaveAcademyCourseRequest $request, AcademyCourse $course): RedirectResponse
    {
        $wasPublished = $course->is_published;
        $course->update($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_course_updated', $course, [
            'content_type' => 'course',
            'old_published' => $wasPublished,
            'new_published' => $course->is_published,
        ]);

        return back()->with('status', 'Course updated.');
    }

    public function storeLesson(SaveAcademyLessonRequest $request): RedirectResponse
    {
        $lesson = AcademyLesson::query()->create($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_lesson_created', $lesson, [
            'content_type' => 'lesson',
            'new_published' => $lesson->is_published,
        ]);

        return redirect()
            ->route('admin.academy.lessons.edit', $lesson)
            ->with('status', 'Lesson created. Add a knowledge check before publishing it if needed.');
    }

    public function editLesson(AcademyLesson $lesson): View
    {
        $lesson->load(['course.category', 'quiz.questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')]);

        return view('admin.academy.lesson', [
            'lesson' => $lesson,
            'courses' => AcademyCourse::query()->with('category')->orderBy('title')->get(),
        ]);
    }

    public function updateLesson(SaveAcademyLessonRequest $request, AcademyLesson $lesson): RedirectResponse
    {
        $wasPublished = $lesson->is_published;
        $lesson->update($this->publishedValue($request->validated()));
        $this->auditLogger->record($request->user(), 'admin.academy_lesson_updated', $lesson, [
            'content_type' => 'lesson',
            'old_published' => $wasPublished,
            'new_published' => $lesson->is_published,
        ]);

        return back()->with('status', 'Lesson updated.');
    }

    public function saveQuiz(SaveAcademyQuizRequest $request, AcademyLesson $lesson): RedirectResponse
    {
        $existingQuiz = $lesson->quiz()->first();
        $wasPublished = $existingQuiz?->is_published ?? false;
        $data = $request->validated();
        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        if ($data['is_published'] && ($existingQuiz?->questions()->count() ?? 0) === 0) {
            throw ValidationException::withMessages([
                'quiz' => 'Add at least one question before publishing this knowledge check.',
            ]);
        }

        $quiz = $lesson->quiz()->updateOrCreate([], $data);
        $this->auditLogger->record($request->user(), 'admin.academy_quiz_saved', $quiz, [
            'content_type' => 'quiz',
            'old_published' => $wasPublished,
            'new_published' => $quiz->is_published,
        ]);

        return back()->with('status', 'Knowledge check settings saved.');
    }

    public function storeQuestion(SaveAcademyQuestionRequest $request, AcademyQuiz $quiz): RedirectResponse
    {
        $question = $quiz->questions()->create($request->validated());
        $this->auditLogger->record($request->user(), 'admin.academy_question_created', $question, [
            'content_type' => 'question',
        ]);

        return back()->with('status', 'Question added.');
    }

    public function updateQuestion(SaveAcademyQuestionRequest $request, AcademyQuiz $quiz, int $question): RedirectResponse
    {
        $academyQuestion = $quiz->questions()->findOrFail($question);
        $academyQuestion->update($request->validated());
        $this->auditLogger->record($request->user(), 'admin.academy_question_updated', $academyQuestion, [
            'content_type' => 'question',
        ]);

        return back()->with('status', 'Question updated.');
    }

    public function destroyQuestion(Request $request, AcademyQuiz $quiz, int $question): RedirectResponse
    {
        if ($quiz->is_published) {
            throw ValidationException::withMessages([
                'quiz' => 'Unpublish the knowledge check before removing a question.',
            ]);
        }

        $academyQuestion = $quiz->questions()->findOrFail($question);
        $academyQuestion->delete();
        $this->auditLogger->record($request->user(), 'admin.academy_question_deleted', $academyQuestion, [
            'content_type' => 'question',
        ]);

        return back()->with('status', 'Question removed.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function publishedValue(array $data): array
    {
        $data['is_published'] = (bool) ($data['is_published'] ?? false);

        return $data;
    }
}
