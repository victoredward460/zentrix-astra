<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyQuizAttempt extends Model
{
    protected $fillable = [
        'user_id',
        'academy_quiz_id',
        'score',
        'question_count',
        'passed',
        'results',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'question_count' => 'integer',
            'passed' => 'boolean',
            'results' => 'array',
            'submitted_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(AcademyQuiz::class, 'academy_quiz_id');
    }
}
