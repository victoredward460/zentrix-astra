<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyLessonProgress extends Model
{
    protected $table = 'academy_lesson_progress';

    protected $fillable = [
        'user_id',
        'academy_lesson_id',
        'last_viewed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'last_viewed_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(AcademyLesson::class, 'academy_lesson_id');
    }
}
