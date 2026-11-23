<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyQuiz extends Model
{
    protected $fillable = [
        'academy_lesson_id',
        'title',
        'pass_percentage',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'pass_percentage' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(AcademyLesson::class, 'academy_lesson_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(AcademyQuizQuestion::class, 'academy_quiz_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AcademyQuizAttempt::class, 'academy_quiz_id');
    }
}
