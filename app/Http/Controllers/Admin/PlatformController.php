<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScanStatus;
use App\Enums\SecurityFindingState;
use App\Http\Controllers\Controller;
use App\Models\AcademyLessonProgress;
use App\Models\AgentThread;
use App\Models\FileCheck;
use App\Models\PlatformAuditEvent;
use App\Models\SafeNavigationCheck;
use App\Models\ScanRun;
use App\Models\SecurityFinding;
use App\Models\User;
use App\Services\Admin\PlatformAuditLogger;
use App\Services\AI\AgentConversationService;
use App\Services\SafeNavigation\ProviderStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'metrics' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'inactive_users' => User::query()->where('is_active', false)->count(),
                'scans' => ScanRun::query()->count(),
                'active_scans' => ScanRun::query()->whereIn('status', [
                    ScanStatus::Requested->value,
                    ScanStatus::Queued->value,
                    ScanStatus::Processing->value,
                ])->count(),
                'open_findings' => SecurityFinding::query()
                    ->where('state', SecurityFindingState::Open->value)
                    ->count(),
                'file_checks' => FileCheck::query()->count(),
                'safe_navigation_checks' => SafeNavigationCheck::query()->count(),
                'agent_threads' => AgentThread::query()->count(),
                'completed_lessons' => AcademyLessonProgress::query()->whereNotNull('completed_at')->count(),
            ],
            'recentScans' => ScanRun::query()
                ->with(['user:id,name,email', 'securityAsset:id,hostname'])
                ->withCount('findings')
                ->latest('requested_at')
                ->limit(8)
                ->get(),
            'recentEvents' => PlatformAuditEvent::query()
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function configuration(
        Request $request,
        AgentConversationService $agentConversationService,
        ProviderStatusService $providerStatusService,
        PlatformAuditLogger $auditLogger,
    ): View {
        $auditLogger->record($request->user(), 'admin.configuration_viewed');

        return view('admin.configuration', [
            'settings' => [
                'Application environment' => config('app.env'),
                'Debug mode' => config('app.debug') ? 'Enabled' : 'Disabled',
                'Database driver' => config('database.default'),
                'Queue connection' => config('queue.default'),
                'Mail transport' => config('mail.default'),
                'Public registration' => config('auth.allow_registration') ? 'Enabled' : 'Disabled',
            ],
            'aiConfigured' => $agentConversationService->isConfigured(),
            'providers' => $providerStatusService->all(),
        ]);
    }

}
