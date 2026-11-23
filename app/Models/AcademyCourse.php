<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademyCourse extends Model
{
    protected $fillable = [
        'academy_category_id',
        'title',
        'slug',
        'summary',
        'level',
        'estimated_minutes',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'estimated_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AcademyCategory::class, 'academy_category_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(AcademyLesson::class);
    }
}
