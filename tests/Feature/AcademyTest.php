<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AcademyCategory;
use App\Models\AcademyCourse;
use App\Models\AcademyLearningMaterial;
use App\Models\AcademyLesson;
use App\Models\AcademyQuizAttempt;
use App\Models\AcademyQuizQuestion;
use App\Models\User;
use Database\Seeders\AcademySeeder;
use App\Services\AI\OpenRouterEndpointDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AcademyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->mock(OpenRouterEndpointDiscovery::class, function ($mock): void {
            $mock->shouldReceive('eligibleProviders')->andReturn(['nvidia']);
        });
        config(['ai.openrouter.require_zdr' => true]);
        $this->seed(AcademySeeder::class);
    }

    public function test_academy_pages_require_authentication_and_only_show_published_content(): void
    {
        $this->get('/academy')->assertRedirect(route('login'));

        $user = User::factory()->create();
        $course = AcademyCourse::query()->where('slug', 'cybersecurity-foundations')->firstOrFail();
        $lesson = $course->lessons()->where('slug', 'security-basics')->firstOrFail();
        $lesson->update(['content' => '<script>alert(1)</script>']);

        $this->actingAs($user)
            ->get('/academy')
            ->assertOk()
            ->assertSee('Cybersecurity Foundations')
            ->assertSee('Everyday Security');

        $this->get(route('academy.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertSee('The security basics')
            ->assertSee('Confidentiality')
            ->assertDontSee('correct_option')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($user->academyLessonProgress()->where('academy_lesson_id', $lesson->id)->firstOrFail()->last_viewed_at);

        $course->update(['is_published' => false]);

        $this->get(route('academy.courses.show', $course->slug))->assertNotFound();
        $this->get(route('academy.lessons.show', [$course->slug, $lesson->slug]))->assertNotFound();
    }

    public function test_published_course_can_be_downloaded_as_a_complete_pdf(): void
    {
        $user = User::factory()->create();
        $course = AcademyCourse::query()->where('slug', 'cybersecurity-foundations')->firstOrFail();

        $this->get(route('academy.courses.download', $course->slug))
            ->assertRedirect(route('login'));

        $response = $this->actingAs($user)
            ->get(route('academy.courses.download', $course->slug))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertStringContainsString('cybersecurity-foundations-course.pdf', $response->headers->get('content-disposition'));

        $course->update(['is_published' => false]);
        $this->get(route('academy.courses.download', $course->slug))->assertNotFound();
    }

    public function test_quiz_answers_are_validated_on_the_server_and_a_pass_completes_the_lesson(): void
    {
        $user = User::factory()->create();
        $course = AcademyCourse::query()->where('slug', 'cybersecurity-foundations')->firstOrFail();
        $lesson = $course->lessons()->where('slug', 'security-basics')->firstOrFail();
        $quiz = $lesson->quiz()->with('questions')->firstOrFail();
        $questions = $quiz->questions->sortBy('sort_order')->values();

        $this->actingAs($user)
            ->get(route('academy.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertDontSee('correct_option');

        $this->post(route('academy.lessons.complete', [$course->slug, $lesson->slug]))
            ->assertRedirect()
            ->assertSessionHasErrors('lesson');
        $this->assertNull($user->academyLessonProgress()->first()->completed_at);

        $this->post(route('academy.quizzes.attempts.store', $quiz), [
            'answers' => [$questions[0]->id => $questions[0]->correct_option],
        ])->assertRedirect()
            ->assertSessionHasErrors('answers');

        $this->assertDatabaseCount('academy_quiz_attempts', 0);

        $failedAnswers = [];
        foreach ($questions as $question) {
            $wrongOption = collect(array_keys($question->options))
                ->first(fn ($option) => $option !== $question->correct_option);
            $failedAnswers[$question->id] = $wrongOption;
        }

        $failedResponse = $this->post(route('academy.quizzes.attempts.store', $quiz), [
            'answers' => $failedAnswers,
        ])->assertRedirect();
        $failedAttempt = AcademyQuizAttempt::query()->firstOrFail();
        $this->assertFalse($failedAttempt->passed);
        $this->assertNull($user->academyLessonProgress()->first()->completed_at);
        $this->get(route('academy.attempts.show', $failedAttempt))
            ->assertOk()
            ->assertSee('Keep learning')
            ->assertSee('Correct answer');

        $correctAnswers = $questions->mapWithKeys(fn (AcademyQuizQuestion $question) => [
            $question->id => $question->correct_option,
        ])->all();

        $passedResponse = $this->post(route('academy.quizzes.attempts.store', $quiz), [
            'answers' => $correctAnswers,
        ])->assertRedirect();
        $passedAttempt = AcademyQuizAttempt::query()->latest('id')->firstOrFail();
        $this->assertTrue($passedAttempt->passed);
        $this->assertSame(100, $passedAttempt->score);
        $this->assertNotNull($user->academyLessonProgress()->first()->fresh()->completed_at);
        $this->get(route('academy.attempts.show', $passedAttempt))
            ->assertOk()
            ->assertSee('Passed')
            ->assertSee('Your answer');
        $this->get(route('academy.courses.show', $course->slug))
            ->assertOk()
            ->assertSee('1 / 3 complete');
    }

    public function test_users_cannot_access_another_users_attempt_history(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $lesson = AcademyLesson::query()->where('slug', 'security-basics')->firstOrFail();
        $quiz = $lesson->quiz()->with('questions')->firstOrFail();
        $question = $quiz->questions->firstOrFail();

        $attempt = $owner->academyQuizAttempts()->create([
            'academy_quiz_id' => $quiz->id,
            'score' => 100,
            'question_count' => 1,
            'passed' => true,
            'results' => [[
                'prompt' => $question->prompt,
                'options' => $question->options,
                'selected_option' => $question->correct_option,
                'correct_option' => $question->correct_option,
                'is_correct' => true,
                'explanation' => $question->explanation,
            ]],
            'submitted_at' => now(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('academy.attempts.show', $attempt))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('academy.attempts.show', $attempt))
            ->assertOk()
            ->assertSee($question->prompt);
    }

    public function test_learning_history_is_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $lesson = AcademyLesson::query()->where('slug', 'security-basics')->firstOrFail();
        $owner->academyLessonProgress()->create([
            'academy_lesson_id' => $lesson->id,
            'last_viewed_at' => now(),
        ]);

        $this->actingAs($otherUser)
            ->get(route('academy.history'))
            ->assertOk()
            ->assertSee('No lessons started yet')
            ->assertDontSee($lesson->title);

        $this->actingAs($owner)
            ->get(route('academy.history'))
            ->assertOk()
            ->assertSee($lesson->title);
    }

    public function test_lessons_without_a_published_quiz_can_be_marked_complete(): void
    {
        $user = User::factory()->create();
        $category = AcademyCategory::query()->firstOrFail();
        $course = AcademyCourse::query()->create([
            'academy_category_id' => $category->id,
            'title' => 'A lesson without a quiz',
            'slug' => 'lesson-without-quiz',
            'summary' => 'A test course.',
            'level' => 'beginner',
            'estimated_minutes' => 5,
            'is_published' => true,
        ]);
        $lesson = $course->lessons()->create([
            'title' => 'Read and complete',
            'slug' => 'read-and-complete',
            'content' => 'Original test lesson.',
            'is_published' => true,
        ]);

        $this->actingAs($user)
            ->post(route('academy.lessons.complete', [$course->slug, $lesson->slug]))
            ->assertRedirect();

        $this->assertNotNull($user->academyLessonProgress()->where('academy_lesson_id', $lesson->id)->firstOrFail()->completed_at);
    }

    public function test_academy_ai_generator_uses_its_output_budget_without_spending_it_on_reasoning(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Phishing Basics',
                            'summary' => 'Learn to recognize suspicious messages.',
                            'lessons' => [[
                                'title' => 'Check the Sender',
                                'summary' => 'Review the sender carefully.',
                                'content' => 'Verify unexpected messages through a trusted channel.',
                                'video_urls' => ['https://www.youtube.com/watch?v=example'],
                                'quiz' => ['questions' => []],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $course = app(\App\Services\Academy\AcademyCourseGenerator::class)
            ->generate('Recognizing phishing messages', 'beginner');

        $this->assertSame('Phishing Basics', $course['title']);
        $this->assertCount(1, $course['lessons']);
        Http::assertSent(fn (Request $request): bool =>
            $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->data()['model'] === 'nvidia/nemotron-3-ultra-550b-a55b:free'
            && $request->data()['max_tokens'] === 2500
            && $request->data()['reasoning'] === ['effort' => 'none']
            && $request->data()['provider']['only'] === ['nvidia']
            && $request->data()['provider']['allow_fallbacks'] === false
            && $request->data()['provider']['max_price'] === ['prompt' => 0, 'completion' => 0]
        );
    }

    public function test_academy_generation_uses_the_saved_model_and_requires_one_time_ai_consent(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3.5-super-vl:free',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'A Safe Academy Draft',
                            'summary' => 'A short defensive security course.',
                            'lessons' => [[
                                'title' => 'Protect Accounts',
                                'summary' => 'Use unique credentials.',
                                'content' => 'Use a password manager and enable multifactor authentication.',
                                'video_urls' => ['https://www.youtube.com/watch?v=example'],
                                'quiz' => ['title' => 'Review', 'questions' => []],
                            ]],
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => UserRole::Administrator])->save();
        $this->actingAs($administrator)
            ->post(route('admin.academy.generate'), [
                'topic' => 'Account security',
                'level' => 'beginner',
            ])
            ->assertSessionHasErrors('ai_generation');
        Http::assertNothingSent();

        $administrator->forceFill([
            'ai_model' => 'nvidia/nemotron-3.5-super-vl:free',
            'ai_data_sharing_consent_at' => now(),
        ])->save();

        $this->post(route('admin.academy.generate'), [
            'topic' => 'Account security',
            'level' => 'beginner',
        ])->assertRedirect();

        $this->assertDatabaseHas('academy_courses', [
            'title' => 'A Safe Academy Draft',
            'is_published' => false,
        ]);
        Http::assertSent(fn (Request $request): bool =>
            $request->data()['model'] === 'nvidia/nemotron-3.5-super-vl:free'
            && $request->data()['reasoning'] === ['effort' => 'none']
            && $request->data()['provider']['only'] === ['nvidia']
            && $request->data()['provider']['allow_fallbacks'] === false
            && $request->data()['provider']['max_price'] === ['prompt' => 0, 'completion' => 0]
        );
    }

    public function test_academy_materials_are_generated_privately_downloaded_and_shared_with_signed_links(): void
    {
        config([
            'services.openrouter.api_key' => 'test-key',
            'services.openrouter.model' => 'nvidia/nemotron-3-ultra-550b-a55b:free',
        ]);
        Storage::fake('academy-materials');
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'model' => 'nvidia/nemotron-3.5-super-vl:free',
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Secure Account Recovery',
                            'introduction' => 'A practical defensive guide.',
                            'sections' => [
                                [
                                    'heading' => 'Verify recovery requests',
                                    'body' => '<script>alert(1)</script>',
                                    'example' => 'Confirm the request through a trusted channel.',
                                    'code' => '',
                                    'takeaways' => ['Never share recovery codes.'],
                                ],
                                [
                                    'heading' => 'Protect recovery factors',
                                    'body' => 'Keep recovery methods current.',
                                    'example' => '',
                                    'code' => '',
                                    'takeaways' => [],
                                ],
                                [
                                    'heading' => 'Review account activity',
                                    'body' => 'Check for unexpected changes.',
                                    'example' => '',
                                    'code' => '',
                                    'takeaways' => [],
                                ],
                            ],
                            'conclusion' => 'Use verified channels and protect recovery secrets.',
                        ], JSON_THROW_ON_ERROR),
                    ],
                ]],
            ]),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('academy.index'))
            ->assertOk()
            ->assertSee('Create an HTML + PDF study guide');

        $this->post(route('academy.materials.generate'), [
            'topic' => 'Secure account recovery',
            'level' => 'beginner',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('ai_material');
        Http::assertNothingSent();

        $user->forceFill([
            'ai_model' => 'nvidia/nemotron-3.5-super-vl:free',
            'ai_data_sharing_consent_at' => now(),
        ])->save();

        $this->post(route('academy.materials.generate'), [
            'topic' => 'Secure account recovery',
            'level' => 'beginner',
        ])
            ->assertRedirect(route('academy.index'))
            ->assertSessionHas('material-share-url');

        $material = AcademyLearningMaterial::query()->firstOrFail();
        $disk = Storage::disk('academy-materials');
        $disk->assertExists($material->html_path);
        $disk->assertExists($material->pdf_path);
        $this->assertNotSame($material->html_path, $material->pdf_path);
        $html = $disk->get($material->html_path);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        Http::assertSent(fn (Request $request): bool =>
            $request->data()['model'] === 'nvidia/nemotron-3.5-super-vl:free'
            && $request->data()['reasoning'] === ['effort' => 'none']
            && $request->data()['provider']['only'] === ['nvidia']
            && $request->data()['provider']['allow_fallbacks'] === false
            && $request->data()['provider']['max_price'] === ['prompt' => 0, 'completion' => 0]
        );

        $shareUrl = URL::signedRoute('academy.materials.shared', [
            'material' => $material->public_id,
        ]);
        $this->get(route('academy.index'))
            ->assertOk()
            ->assertSee($shareUrl, false)
            ->assertSee('Download HTML')
            ->assertSee('Download PDF');

        auth()->logout();
        $this->get($shareUrl)
            ->assertOk()
            ->assertSee('Secure Account Recovery')
            ->assertSee('Download PDF')
            ->assertDontSee($material->html_path);
        $this->get(URL::signedRoute('academy.materials.shared.download', [
            'material' => $material->public_id,
            'format' => 'html',
        ]))->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $pdfResponse = $this->get(URL::signedRoute('academy.materials.shared.download', [
            'material' => $material->public_id,
            'format' => 'pdf',
        ]))->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdfResponse->streamedContent());
        $this->get(route('academy.materials.shared', ['material' => $material->public_id]))
            ->assertForbidden();

        $otherUser = User::factory()->create();
        $this->actingAs($otherUser)
            ->get(route('academy.materials.download', [$material, 'pdf']))
            ->assertNotFound();
        $this->delete(route('academy.materials.destroy', $material))->assertNotFound();

        $this->actingAs($user)
            ->get(route('academy.materials.download', [$material, 'html']))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->delete(route('academy.materials.destroy', $material))
            ->assertRedirect();
        $this->assertDatabaseMissing('academy_learning_materials', ['id' => $material->id]);
        $disk->assertMissing($material->html_path);
        $disk->assertMissing($material->pdf_path);
        $this->get($shareUrl)->assertNotFound();
    }

    public function test_administrators_manage_content_and_quizzes_while_users_are_denied(): void
    {
        $administrator = User::factory()->create();
        $administrator->forceFill(['role' => UserRole::Administrator])->save();
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.academy.index'))->assertForbidden();

        $this->actingAs($administrator)
            ->get(route('admin.academy.index'))
            ->assertOk()
            ->assertSee('Academy content')
            ->assertSee('Create a course')
            ->assertSee('Generate an HTML and PDF study guide')
            ->assertSee('anyone with that link can view the guide');

        $this->post(route('admin.academy.categories.store'), [
            'name' => 'Admin-created category',
            'slug' => 'admin-created-category',
            'description' => 'A managed learning category.',
            'sort_order' => 10,
        ])->assertRedirect();
        $category = AcademyCategory::query()->where('slug', 'admin-created-category')->firstOrFail();

        $this->put(route('admin.academy.categories.update', $category), [
            'name' => 'Updated category',
            'slug' => 'updated-category',
            'description' => 'Updated description.',
            'sort_order' => 20,
            'is_published' => 1,
        ])->assertRedirect();
        $category->refresh();
        $this->assertTrue($category->is_published);

        $this->post(route('admin.academy.courses.store'), [
            'academy_category_id' => $category->id,
            'title' => 'Admin-created course',
            'slug' => 'admin-created-course',
            'summary' => 'Course summary.',
            'level' => 'beginner',
            'estimated_minutes' => 12,
            'sort_order' => 10,
        ])->assertRedirect();
        $course = AcademyCourse::query()->where('slug', 'admin-created-course')->firstOrFail();
        $this->put(route('admin.academy.courses.update', $course), [
            'academy_category_id' => $category->id,
            'title' => 'Updated course',
            'slug' => 'admin-created-course',
            'summary' => 'Updated course summary.',
            'level' => 'beginner',
            'estimated_minutes' => 15,
            'sort_order' => 10,
            'is_published' => 1,
        ])->assertRedirect();
        $course->refresh();
        $this->assertTrue($course->is_published);

        $this->post(route('admin.academy.lessons.store'), [
            'academy_course_id' => $course->id,
            'title' => 'Admin-created lesson',
            'slug' => 'admin-created-lesson',
            'summary' => 'Lesson summary.',
            'content' => 'Original educational content.',
            'sort_order' => 10,
        ])->assertRedirect();
        $lesson = AcademyLesson::query()->where('slug', 'admin-created-lesson')->firstOrFail();
        $this->get(route('admin.academy.lessons.edit', $lesson))
            ->assertOk()
            ->assertSee('Admin-created lesson');
        $this->put(route('admin.academy.lessons.update', $lesson), [
            'academy_course_id' => $course->id,
            'title' => 'Updated lesson title',
            'slug' => 'admin-created-lesson',
            'summary' => 'Updated lesson summary.',
            'content' => 'Updated original educational content.',
            'sort_order' => 20,
            'is_published' => 1,
        ])->assertRedirect();
        $lesson->refresh();
        $this->assertTrue($lesson->is_published);

        $this->post(route('admin.academy.quizzes.save', $lesson), [
            'title' => 'New quiz',
            'pass_percentage' => 70,
            'is_published' => 1,
        ])->assertRedirect()
            ->assertSessionHasErrors('quiz');
        $this->assertDatabaseMissing('academy_quizzes', ['academy_lesson_id' => $lesson->id]);

        $this->post(route('admin.academy.quizzes.save', $lesson), [
            'title' => 'New quiz',
            'pass_percentage' => 70,
        ])->assertRedirect();
        $quiz = $lesson->quiz()->firstOrFail();

        $this->post(route('admin.academy.questions.store', $quiz), [
            'prompt' => 'Which is a safer habit?',
            'options' => [
                'a' => 'Reuse a password',
                'b' => 'Use unique passwords',
                'c' => 'Share passwords',
                'd' => 'Disable updates',
            ],
            'correct_option' => 'b',
            'explanation' => 'Unique passwords reduce reuse risk.',
            'sort_order' => 10,
        ])->assertRedirect();

        $this->post(route('admin.academy.quizzes.save', $lesson), [
            'title' => 'New quiz',
            'pass_percentage' => 70,
            'is_published' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('academy_quizzes', [
            'academy_lesson_id' => $lesson->id,
            'is_published' => true,
        ]);
        $this->assertDatabaseHas('academy_quiz_questions', [
            'academy_quiz_id' => $quiz->id,
            'correct_option' => 'b',
        ]);
    }
}
