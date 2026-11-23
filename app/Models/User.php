<?php

namespace App\Models;

use App\Enums\UserRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'user',
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'ai_model',
        'ai_data_sharing_consent_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'ai_data_sharing_consent_at' => 'datetime',
        ];
    }

    public function aiModel(): string
    {
        $models = (array) config('ai.openrouter.models', []);
        $selectedModel = $this->getAttribute('ai_model');

        if (is_string($selectedModel) && array_key_exists($selectedModel, $models)) {
            return $selectedModel;
        }

        $defaultModel = config('services.openrouter.model');

        if (is_string($defaultModel) && trim($defaultModel) !== '') {
            return trim($defaultModel);
        }

        return (string) array_key_first($models);
    }

    public function hasAiProviderConsent(): bool
    {
        return $this->ai_data_sharing_consent_at !== null;
    }

    public function isAdministrator(): bool
    {
        return $this->role === UserRole::Administrator;
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if (($role instanceof UserRole ? $role->value : $role) === $this->role->value) {
                return true;
            }
        }

        return false;
    }

    public function securityAssets(): HasMany
    {
        return $this->hasMany(SecurityAsset::class);
    }

    public function scanRuns(): HasMany
    {
        return $this->hasMany(ScanRun::class);
    }

    public function securityFindings(): HasMany
    {
        return $this->hasMany(SecurityFinding::class);
    }

    public function fileChecks(): HasMany
    {
        return $this->hasMany(FileCheck::class);
    }

    public function safeNavigationChecks(): HasMany
    {
        return $this->hasMany(SafeNavigationCheck::class);
    }

    public function agentThreads(): HasMany
    {
        return $this->hasMany(AgentThread::class);
    }

    public function academyLessonProgress(): HasMany
    {
        return $this->hasMany(AcademyLessonProgress::class);
    }

    public function academyQuizAttempts(): HasMany
    {
        return $this->hasMany(AcademyQuizAttempt::class);
    }

    public function academyLearningMaterials(): HasMany
    {
        return $this->hasMany(AcademyLearningMaterial::class);
    }
}
