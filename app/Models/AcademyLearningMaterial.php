<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyLearningMaterial extends Model
{
    protected $fillable = [
        'user_id',
        'public_id',
        'title',
        'topic',
        'level',
        'content',
        'html_path',
        'pdf_path',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
