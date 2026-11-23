<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AcademyLesson extends Model
{
    protected $fillable = [
        'academy_course_id',
        'title',
        'slug',
        'summary',
        'content',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(AcademyCourse::class, 'academy_course_id');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(AcademyQuiz::class, 'academy_lesson_id');
    }

    public function progress(): HasMany
    {
        return $this->hasMany(AcademyLessonProgress::class, 'academy_lesson_id');
    }
}
