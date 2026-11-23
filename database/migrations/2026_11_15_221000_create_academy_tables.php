<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COURSE_SLUG_INDEX = 'academy_courses_slug_unique';

    private const COURSE_FILTER_INDEX = 'ac_courses_cat_pub_sort_idx';

    public function up(): void
    {
        $this->createOrValidateCategories();
        $this->createOrRepairCourses();

        Schema::create('academy_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_course_id')->constrained('academy_courses')->cascadeOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180);
            $table->text('summary')->nullable();
            $table->longText('content');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            $table->unique(['academy_course_id', 'slug']);
            $table->index(['academy_course_id', 'is_published', 'sort_order']);
        });

        Schema::create('academy_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_lesson_id')->unique()->constrained('academy_lessons')->cascadeOnDelete();
            $table->string('title', 160);
            $table->unsignedTinyInteger('pass_percentage')->default(70);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });

        Schema::create('academy_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_quiz_id')->constrained('academy_quizzes')->cascadeOnDelete();
            $table->text('prompt');
            $table->json('options');
            $table->string('correct_option', 1);
            $table->text('explanation');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['academy_quiz_id', 'sort_order']);
        });

        Schema::create('academy_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academy_lesson_id')->constrained('academy_lessons')->cascadeOnDelete();
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'academy_lesson_id']);
            $table->index(['user_id', 'completed_at', 'updated_at']);
        });

        Schema::create('academy_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academy_quiz_id')->constrained('academy_quizzes')->cascadeOnDelete();
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('question_count');
            $table->boolean('passed');
            $table->json('results');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'submitted_at']);
            $table->index(['user_id', 'academy_quiz_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academy_quiz_attempts');
        Schema::dropIfExists('academy_lesson_progress');
        Schema::dropIfExists('academy_quiz_questions');
        Schema::dropIfExists('academy_quizzes');
        Schema::dropIfExists('academy_lessons');
        Schema::dropIfExists('academy_courses');
        Schema::dropIfExists('academy_categories');
    }

    private function createOrValidateCategories(): void
    {
        if (Schema::hasTable('academy_categories')) {
            $this->assertColumns('academy_categories', [
                'id',
                'name',
                'slug',
                'description',
                'sort_order',
                'is_published',
                'created_at',
                'updated_at',
            ]);
            $this->ensureIndex('academy_categories', 'academy_categories_slug_unique', ['slug'], true);

            return;
        }

        Schema::create('academy_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    private function createOrRepairCourses(): void
    {
        if (! Schema::hasTable('academy_courses')) {
            Schema::create('academy_courses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('academy_category_id')->constrained('academy_categories')->restrictOnDelete();
                $table->string('title', 160);
                $table->string('slug', 180);
                $table->text('summary');
                $table->string('level', 20)->default('beginner');
                $table->unsignedSmallInteger('estimated_minutes')->default(10);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_published')->default(false);
                $table->timestamps();

                $table->unique('slug', self::COURSE_SLUG_INDEX);
                $table->index(
                    ['academy_category_id', 'is_published', 'sort_order'],
                    self::COURSE_FILTER_INDEX,
                );
            });

            return;
        }

        $this->assertColumns('academy_courses', [
            'id',
            'academy_category_id',
            'title',
            'slug',
            'summary',
            'level',
            'estimated_minutes',
            'sort_order',
            'is_published',
            'created_at',
            'updated_at',
        ]);
        $this->assertAcademyCourseCategoryForeignKey();
        $this->ensureIndex('academy_courses', self::COURSE_SLUG_INDEX, ['slug'], true);
        $this->ensureIndex(
            'academy_courses',
            self::COURSE_FILTER_INDEX,
            ['academy_category_id', 'is_published', 'sort_order'],
            false,
        );
    }

    private function assertColumns(string $table, array $expected): void
    {
        $actual = Schema::getColumnListing($table);

        if ($actual !== $expected) {
            throw new RuntimeException(sprintf(
                'Cannot safely resume the Academy migration: table [%s] has unexpected columns.',
                $table,
            ));
        }
    }

    private function assertAcademyCourseCategoryForeignKey(): void
    {
        $foreignKeys = array_values(array_filter(
            Schema::getForeignKeys('academy_courses'),
            fn (array $foreignKey): bool => $foreignKey['columns'] === ['academy_category_id'],
        ));

        if (
            count($foreignKeys) !== 1
            || $foreignKeys[0]['foreign_table'] !== 'academy_categories'
            || $foreignKeys[0]['foreign_columns'] !== ['id']
            || $foreignKeys[0]['on_delete'] !== 'restrict'
        ) {
            throw new RuntimeException(
                'Cannot safely resume the Academy migration: the academy_courses category foreign key is unexpected.',
            );
        }
    }

    private function ensureIndex(string $table, string $name, array $columns, bool $unique): void
    {
        foreach (Schema::getIndexes($table) as $index) {
            if ($index['name'] === $name) {
                if ($index['columns'] !== $columns || $index['unique'] !== $unique) {
                    throw new RuntimeException(sprintf(
                        'Cannot safely resume the Academy migration: index [%s] on [%s] has an unexpected definition.',
                        $name,
                        $table,
                    ));
                }

                return;
            }

            if ($index['columns'] === $columns && $index['unique'] === $unique) {
                throw new RuntimeException(sprintf(
                    'Cannot safely resume the Academy migration: an equivalent index exists on [%s] under another name.',
                    $table,
                ));
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name, $columns, $unique) {
            if ($unique) {
                $blueprint->unique($columns, $name);

                return;
            }

            $blueprint->index($columns, $name);
        });
    }
};
