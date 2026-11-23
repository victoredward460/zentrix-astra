<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Models\AcademyCategory;
use App\Models\AcademyCourse;
use App\Models\AcademyLesson;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class AcademyController extends Controller
{
    public function index(Request $request): View
    {
        $categories = AcademyCategory::query()
            ->published()
            ->whereHas('courses', fn ($query) => $query->published())
            ->with(['courses' => function ($query) {
                $query->published()
                    ->withCount(['lessons' => fn ($lessons) => $lessons->published()])
                    ->orderBy('sort_order')
                    ->orderBy('title');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('academy.index', [
            'categories' => $categories,
            'completedLessonIds' => $this->completedLessonIds($request),
            'aiModels' => (array) config('ai.openrouter.models', []),
            'aiModel' => $request->user()->aiModel(),
            'aiProviderConsent' => $request->user()->hasAiProviderConsent(),
            'materials' => $request->user()->academyLearningMaterials()->latest()->limit(30)->get(),
        ]);
    }

    public function showCourse(Request $request, string $courseSlug): View
    {
        $course = AcademyCourse::query()
            ->published()
            ->where('slug', $courseSlug)
            ->whereHas('category', fn ($query) => $query->published())
            ->with(['category', 'lessons' => function ($query) {
                $query->published()
                    ->with('quiz')
                    ->orderBy('sort_order')
                    ->orderBy('title');
            }])
            ->firstOrFail();

        $completedLessonIds = $this->completedLessonIds($request);

        return view('academy.course', [
            'course' => $course,
            'completedLessonIds' => $completedLessonIds,
            'completedCount' => $course->lessons->whereIn('id', $completedLessonIds)->count(),
        ]);
    }

    public function downloadCoursePdf(string $courseSlug)
    {
        $course = $this->publishedCourse($courseSlug);
        $course->load([
            'category',
            'lessons' => fn ($query) => $query
                ->published()
                ->with([
                    'quiz' => fn ($quiz) => $quiz
                        ->where('is_published', true)
                        ->with(['questions' => fn ($questions) => $questions->orderBy('sort_order')]),
                ])
                ->orderBy('sort_order')
                ->orderBy('title'),
        ]);

        return Pdf::loadView('academy.course-pdf', ['course' => $course])
            ->setPaper('a4')
            ->download(str($course->slug)->slug().'-course.pdf');
    }

    public function showLesson(Request $request, string $courseSlug, string $lessonSlug): View
    {
        $course = $this->publishedCourse($courseSlug);
        $lesson = $course->lessons()
            ->published()
            ->where('slug', $lessonSlug)
            ->with(['quiz.questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->firstOrFail();

        $user = $request->user();
        $progress = $user->academyLessonProgress()->firstOrCreate(
            ['academy_lesson_id' => $lesson->id],
            ['last_viewed_at' => now()],
        );
        $progress->forceFill(['last_viewed_at' => now()])->save();

        $quiz = $lesson->quiz?->is_published ? $lesson->quiz : null;
        $quizQuestions = $quiz?->questions->map(fn ($question) => [
            'id' => $question->id,
            'prompt' => $question->prompt,
            'options' => $question->options,
        ]) ?? collect();

        $lessons = $course->lessons()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id', 'slug', 'title']);
        $lessonIndex = $lessons->search(fn (AcademyLesson $item) => $item->is($lesson));

        return view('academy.lesson', [
            'course' => $course,
            'lesson' => $lesson,
            'progress' => $progress,
            'quiz' => $quiz,
            'quizQuestions' => $quizQuestions,
            'recentAttempts' => $quiz === null
                ? collect()
                : $user->academyQuizAttempts()
                    ->where('academy_quiz_id', $quiz->id)
                    ->latest('submitted_at')
                    ->limit(5)
                    ->get(),
            'previousLesson' => $lessonIndex > 0 ? $lessons[$lessonIndex - 1] : null,
            'nextLesson' => $lessonIndex !== false && isset($lessons[$lessonIndex + 1]) ? $lessons[$lessonIndex + 1] : null,
        ]);
    }

    public function completeLesson(Request $request, string $courseSlug, string $lessonSlug): RedirectResponse
    {
        $course = $this->publishedCourse($courseSlug);
        $lesson = $course->lessons()->published()->where('slug', $lessonSlug)->firstOrFail();

        if ($lesson->quiz()->where('is_published', true)->exists()) {
            return back()->withErrors([
                'lesson' => 'Pass the published knowledge check to complete this lesson.',
            ]);
        }

        $progress = $request->user()->academyLessonProgress()->firstOrCreate(
            ['academy_lesson_id' => $lesson->id],
            ['last_viewed_at' => now()],
        );
        $progress->forceFill([
            'last_viewed_at' => now(),
            'completed_at' => $progress->completed_at ?? now(),
        ])->save();

        return back()->with('status', 'Lesson marked complete.');
    }

    public function history(Request $request): View
    {
        $user = $request->user();

        return view('academy.history', [
            'progress' => $user->academyLessonProgress()
                ->with(['lesson.course'])
                ->latest('updated_at')
                ->paginate(15, ['*'], 'progress_page'),
            'attempts' => $user->academyQuizAttempts()
                ->with(['quiz.lesson.course'])
                ->latest('submitted_at')
                ->paginate(15, ['*'], 'attempt_page'),
        ]);
    }

    public function showAttempt(Request $request, int $attempt): View
    {
        $attemptRecord = $request->user()->academyQuizAttempts()
            ->with(['quiz.lesson.course'])
            ->findOrFail($attempt);

        return view('academy.attempt', [
            'attempt' => $attemptRecord,
        ]);
    }

    private function publishedCourse(string $courseSlug): AcademyCourse
    {
        return AcademyCourse::query()
            ->published()
            ->where('slug', $courseSlug)
            ->whereHas('category', fn ($query) => $query->published())
            ->firstOrFail();
    }

    /**
     * @return Collection<int, int>
     */
    private function completedLessonIds(Request $request): Collection
    {
        return $request->user()
            ->academyLessonProgress()
            ->whereNotNull('completed_at')
            ->pluck('academy_lesson_id')
            ->map(fn ($id) => (int) $id);
    }
}
