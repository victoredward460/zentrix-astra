<?php

use App\Http\Controllers\Academy\AcademyController;
use App\Http\Controllers\Academy\AcademyMaterialController;
use App\Http\Controllers\Academy\AcademyQuizController;
use App\Http\Controllers\Admin\AcademyContentController;
use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\ModuleRecordsController;
use App\Http\Controllers\Admin\PlatformController;
use App\Http\Controllers\Admin\PlatformFeatureController;
use App\Http\Controllers\Admin\SafeNavigationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FileCheck\FileCheckController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Scanner\ScanRunController;
use App\Http\Controllers\Scanner\SubmittedUrlController;
use App\Http\Controllers\Scanner\SecurityAssetController;
use App\Http\Controllers\WebsiteAuditor\WebsiteAuditController;
use App\Http\Controllers\WebsiteAuditor\DirectWebsiteAuditController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/health/ready', [HealthController::class, 'readiness'])->name('health.ready');

Route::get('/academy/materials/{material:public_id}', [AcademyMaterialController::class, 'showShared'])
    ->middleware(['signed', 'platform.feature:academy'])
    ->name('academy.materials.shared');
Route::get('/academy/materials/{material:public_id}/download/{format}', [AcademyMaterialController::class, 'downloadShared'])
    ->where('format', 'html|pdf')
    ->middleware(['signed', 'platform.feature:academy'])
    ->name('academy.materials.shared.download');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('register.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware(['auth', 'active'])
    ->name('logout');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::prefix('academy')->name('academy.')->middleware('platform.feature:academy')->group(function () {
        Route::get('/', [AcademyController::class, 'index'])->name('index');
        Route::get('/history', [AcademyController::class, 'history'])->name('history');
        Route::get('/attempts/{attempt}', [AcademyController::class, 'showAttempt'])->name('attempts.show');
        Route::post('/materials/generate', [AcademyMaterialController::class, 'generate'])
            ->middleware('throttle:academy-quizzes')
            ->name('materials.generate');
        Route::get('/my-materials/{material}/download/{format}', [AcademyMaterialController::class, 'downloadOwned'])
            ->where('format', 'html|pdf')
            ->name('materials.download');
        Route::delete('/materials/{material}', [AcademyMaterialController::class, 'destroy'])
            ->name('materials.destroy');
        Route::post('/quizzes/{quiz}/attempts', [AcademyQuizController::class, 'store'])
            ->middleware('throttle:academy-quizzes')
            ->name('quizzes.attempts.store');
        Route::get('/courses/{courseSlug}/download', [AcademyController::class, 'downloadCoursePdf'])
            ->name('courses.download');
        Route::get('/courses/{courseSlug}', [AcademyController::class, 'showCourse'])->name('courses.show');
        Route::get('/courses/{courseSlug}/lessons/{lessonSlug}', [AcademyController::class, 'showLesson'])
            ->name('lessons.show');
        Route::post('/courses/{courseSlug}/lessons/{lessonSlug}/complete', [AcademyController::class, 'completeLesson'])
            ->name('lessons.complete');
    });

    Route::middleware('platform.feature:scanner')->group(function (): void {
        Route::get('/scanner', [SecurityAssetController::class, 'index'])->name('scanner.assets.index');
        Route::post('/scanner/url', [SubmittedUrlController::class, 'store'])
            ->middleware('throttle:safe-navigation-checks')
            ->name('scanner.url.store');
        Route::post('/scanner/assets', [SecurityAssetController::class, 'store'])
            ->middleware('throttle:scanner-assets')
            ->name('scanner.assets.store');
        Route::post('/scanner/assets/{securityAsset}/verify', [SecurityAssetController::class, 'verify'])
            ->middleware('throttle:scanner-verification')
            ->name('scanner.assets.verify');
        Route::post('/scanner/assets/{securityAsset}/challenge', [SecurityAssetController::class, 'refreshChallenge'])
            ->middleware('throttle:scanner-verification')
            ->name('scanner.assets.challenge');
        Route::post('/scanner/assets/{securityAsset}/scans', [ScanRunController::class, 'store'])
            ->middleware('throttle:security-scans')
            ->name('scanner.assets.scans.store');
        Route::get('/scanner/runs/{scanRun}', [ScanRunController::class, 'show'])->name('scanner.runs.show');
    });

    Route::middleware('platform.feature:website-auditor')->group(function (): void {
        Route::get('/website-auditor', [WebsiteAuditController::class, 'index'])->name('website-auditor.index');
        Route::post('/website-auditor/url', [DirectWebsiteAuditController::class, 'store'])
            ->middleware('throttle:website-audits')
            ->name('website-auditor.url.store');
        Route::post('/website-auditor/assets/{securityAsset}/assess', [WebsiteAuditController::class, 'store'])
            ->middleware('throttle:website-audits')
            ->name('website-auditor.assets.assess');
        Route::get('/website-auditor/runs/{scanRun}', [WebsiteAuditController::class, 'show'])
            ->name('website-auditor.runs.show');
    });

    Route::middleware('platform.feature:file-check')->group(function (): void {
        Route::get('/file-checks', [FileCheckController::class, 'index'])->name('file-check.index');
        Route::post('/file-checks', [FileCheckController::class, 'store'])
            ->middleware('throttle:file-checks')
            ->name('file-check.store');
        Route::post('/file-checks/message-analysis', [FileCheckController::class, 'analyzeMessage'])
            ->middleware('throttle:file-checks')
            ->name('file-check.message-analysis');
        Route::post('/file-checks/{fileCheck}/explain-ai', [FileCheckController::class, 'explainFileWithAI'])
            ->middleware('throttle:agent-messages')
            ->name('file-check.ai-explanation');
        Route::get('/file-checks/{fileCheck}', [FileCheckController::class, 'show'])->name('file-check.show');
        Route::post('/file-checks/{fileCheck}/virustotal', [FileCheckController::class, 'checkVirusTotal'])
            ->middleware('throttle:virustotal-lookups')
            ->name('file-check.virustotal');
        Route::delete('/file-checks/{fileCheck}', [FileCheckController::class, 'destroy'])->name('file-check.destroy');
    });

    Route::prefix('agent')->name('agent.')->middleware('platform.feature:ai-agent')->group(function () {
        Route::get('/', [AgentController::class, 'index'])->name('index');
        Route::patch('/model', [AgentController::class, 'updateModel'])->name('model.update');
        Route::post('/threads', [AgentController::class, 'store'])->name('threads.store');
        Route::post('/threads/start', [AgentController::class, 'start'])
            ->middleware('throttle:agent-messages')
            ->name('threads.start');
        Route::get('/threads/{thread}', [AgentController::class, 'show'])->name('threads.show');
        Route::post('/threads/{thread}/messages', [AgentController::class, 'send'])
            ->middleware('throttle:agent-messages')
            ->name('threads.messages.store');
        Route::patch('/threads/{thread}/title', [AgentController::class, 'rename'])->name('threads.rename');
        Route::delete('/threads/{thread}', [AgentController::class, 'destroy'])->name('threads.destroy');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/ai-preferences', [ProfileController::class, 'updateAiPreferences'])
        ->name('profile.ai-preferences.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    Route::prefix('safe-navigation')->name('safe-navigation.')->middleware('role:admin')->group(function () {
        Route::get('/', [SafeNavigationController::class, 'index'])->name('index');
        Route::post('/checks', [SafeNavigationController::class, 'store'])
            ->middleware('throttle:safe-navigation-checks')
            ->name('checks.store');
        Route::get('/checks/{safeNavigationCheck}', [SafeNavigationController::class, 'show'])
            ->name('checks.show');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('/', [PlatformController::class, 'dashboard'])->name('dashboard');
        Route::get('/features', [PlatformFeatureController::class, 'index'])->name('features.index');
        Route::patch('/features/{feature}', [PlatformFeatureController::class, 'update'])->name('features.update');
        Route::get('/configuration', [PlatformController::class, 'configuration'])->name('configuration');
        Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity.index');
        Route::get('/security-events', [ActivityLogController::class, 'security'])->name('security-events.index');
        Route::get('/scans', [ModuleRecordsController::class, 'scans'])->name('scans.index');
        Route::get('/scans/{scanRun}', [ModuleRecordsController::class, 'showScan'])->name('scans.show');
        Route::get('/file-checks', [ModuleRecordsController::class, 'fileChecks'])->name('file-checks.index');
        Route::get('/file-checks/{fileCheck}', [ModuleRecordsController::class, 'showFileCheck'])->name('file-checks.show');
        Route::get('/academy/progress', [ModuleRecordsController::class, 'academyProgress'])->name('academy.progress');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::put('/users/{user}/role', [UserController::class, 'updateRole'])->name('users.role.update');
        Route::put('/users/{user}/status', [UserController::class, 'updateStatus'])->name('users.status.update');

        Route::prefix('academy')->name('academy.')->group(function () {
            Route::get('/', [AcademyContentController::class, 'index'])->name('index');
            Route::post('/generate', [AcademyContentController::class, 'generate'])
                ->middleware('throttle:academy-quizzes')
                ->name('generate');
            Route::post('/materials/generate', [AcademyMaterialController::class, 'generate'])
                ->middleware('throttle:academy-quizzes')
                ->name('materials.generate');
            Route::get('/materials/{material}/download/{format}', [AcademyMaterialController::class, 'downloadOwned'])
                ->where('format', 'html|pdf')
                ->name('materials.download');
            Route::delete('/materials/{material}', [AcademyMaterialController::class, 'destroy'])
                ->name('materials.destroy');
            Route::post('/categories', [AcademyContentController::class, 'storeCategory'])->name('categories.store');
            Route::put('/categories/{category}', [AcademyContentController::class, 'updateCategory'])->name('categories.update');
            Route::post('/courses', [AcademyContentController::class, 'storeCourse'])->name('courses.store');
            Route::put('/courses/{course}', [AcademyContentController::class, 'updateCourse'])->name('courses.update');
            Route::post('/lessons', [AcademyContentController::class, 'storeLesson'])->name('lessons.store');
            Route::get('/lessons/{lesson}/edit', [AcademyContentController::class, 'editLesson'])->name('lessons.edit');
            Route::put('/lessons/{lesson}', [AcademyContentController::class, 'updateLesson'])->name('lessons.update');
            Route::post('/lessons/{lesson}/quiz', [AcademyContentController::class, 'saveQuiz'])->name('quizzes.save');
            Route::post('/quizzes/{quiz}/questions', [AcademyContentController::class, 'storeQuestion'])->name('questions.store');
            Route::put('/quizzes/{quiz}/questions/{question}', [AcademyContentController::class, 'updateQuestion'])->name('questions.update');
            Route::delete('/quizzes/{quiz}/questions/{question}', [AcademyContentController::class, 'destroyQuestion'])->name('questions.destroy');
        });
    });
});
