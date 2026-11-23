<?php

namespace App\Http\Controllers\Academy;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAcademyQuizRequest;
use App\Models\AcademyQuiz;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcademyQuizController extends Controller
{
    public function store(SubmitAcademyQuizRequest $request, int $quiz): RedirectResponse
    {
        $quizRecord = AcademyQuiz::query()
            ->where('is_published', true)
            ->whereHas('lesson', fn ($lesson) => $lesson
                ->where('is_published', true)
                ->whereHas('course', fn ($course) => $course
                    ->where('is_published', true)
                    ->whereHas('category', fn ($category) => $category->published())))
            ->with(['lesson', 'questions' => fn ($query) => $query->orderBy('sort_order')->orderBy('id')])
            ->findOrFail($quiz);

        $questions = $quizRecord->questions;
        if ($questions->isEmpty()) {
            abort(404);
        }

        $answers = $request->validated('answers');
        $expectedIds = $questions->pluck('id')->map(fn ($id) => (string) $id)->all();
        $submittedIds = array_map('strval', array_keys($answers));
        sort($expectedIds);
        sort($submittedIds);

        if ($expectedIds !== $submittedIds) {
            throw ValidationException::withMessages([
                'answers' => 'Answer every question in this knowledge check.',
            ]);
        }

        $correctCount = 0;
        $results = [];

        foreach ($questions as $question) {
            $selectedOption = $answers[$question->id] ?? null;

            if (! array_key_exists($selectedOption, $question->options)) {
                throw ValidationException::withMessages([
                    "answers.{$question->id}" => 'Choose one of the options shown.',
                ]);
            }

            $isCorrect = $selectedOption === $question->correct_option;
            $correctCount += (int) $isCorrect;
            $results[] = [
                'prompt' => $question->prompt,
                'options' => $question->options,
                'selected_option' => $selectedOption,
                'correct_option' => $question->correct_option,
                'is_correct' => $isCorrect,
                'explanation' => $question->explanation,
            ];
        }

        $score = (int) round(($correctCount / $questions->count()) * 100);
        $passed = $score >= $quizRecord->pass_percentage;

        $attempt = DB::transaction(function () use ($request, $quizRecord, $questions, $score, $passed, $results) {
            $attempt = $request->user()->academyQuizAttempts()->create([
                'academy_quiz_id' => $quizRecord->id,
                'score' => $score,
                'question_count' => $questions->count(),
                'passed' => $passed,
                'results' => $results,
                'submitted_at' => now(),
            ]);

            if ($passed) {
                $progress = $request->user()->academyLessonProgress()->firstOrCreate(
                    ['academy_lesson_id' => $quizRecord->academy_lesson_id],
                    ['last_viewed_at' => now()],
                );
                $progress->forceFill([
                    'last_viewed_at' => now(),
                    'completed_at' => $progress->completed_at ?? now(),
                ])->save();
            }

            return $attempt;
        });

        return redirect()->route('academy.attempts.show', $attempt->id);
    }
}
