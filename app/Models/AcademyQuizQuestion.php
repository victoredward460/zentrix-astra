<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyQuizQuestion extends Model
{
    protected $fillable = [
        'academy_quiz_id',
        'prompt',
        'options',
        'correct_option',
        'explanation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(AcademyQuiz::class, 'academy_quiz_id');
    }
}
