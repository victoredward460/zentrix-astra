<?php

namespace App\Services;

use App\Data\DashboardActivity;
use App\Data\DashboardFinding;
use App\Data\DashboardMetrics;
use App\Data\DashboardScan;
use App\Data\DashboardSnapshot;
use App\Enums\ScanStatus;
use App\Enums\ScanType;
use App\Enums\SecurityFindingState;
use App\Models\FileCheck;
use App\Models\ScanRun;
use App\Models\User;

class DashboardService
{
    public function forUser(User $user): DashboardSnapshot
    {
        $scanQuery = $user->scanRuns()->with('securityAsset')->withCount('findings');
        $scanRecords = (clone $scanQuery)->latest()->limit(8)->get();
        $fileCheckRecords = $user->fileChecks()->latest()->limit(8)->get();
        $recentFindings = $user->securityFindings()
            ->latest('detected_at')
            ->limit(8)
            ->get()
            ->map(fn ($finding) => new DashboardFinding(
                title: $finding->title,
                severity: str($finding->severity->value)->headline()->toString(),
                verificationState: str($finding->verification_state->value)->headline()->toString(),
                recordedAt: $finding->detected_at->toImmutable(),
            ));

        $scanActivity = $scanRecords
            ->map(fn (ScanRun $scanRun) => new DashboardActivity(
                title: str($scanRun->scan_type === ScanType::WebsiteAudit ? 'Website audit' : 'Passive scan')
                    ->append(' ')
                    ->append(str($scanRun->status->value)->headline()->lower())
                    ->toString(),
                description: 'Assessment for '.$scanRun->securityAsset->hostname.'.',
                occurredAt: ($scanRun->finished_at ?? $scanRun->started_at ?? $scanRun->requested_at)->toImmutable(),
            ));
        $fileActivity = $fileCheckRecords->map(fn (FileCheck $fileCheck) => new DashboardActivity(
            title: 'File Check '.str($fileCheck->status->value)->headline()->lower(),
            description: 'Metadata/hash record for '.$fileCheck->original_name.'; no malware analysis was performed.',
            occurredAt: ($fileCheck->processed_at ?? $fileCheck->created_at)->toImmutable(),
        ));
        $timeline = $scanActivity
            ->concat($fileActivity)
            ->sortByDesc(fn (DashboardActivity $activity) => $activity->occurredAt)
            ->take(8)
            ->values();

        $scanHistory = $scanRecords->map(fn (ScanRun $scanRun) => new DashboardScan(
            module: $scanRun->scan_type === ScanType::WebsiteAudit ? 'Website auditor' : 'Security scanner',
            target: $scanRun->securityAsset->hostname,
            status: str($scanRun->status->value)->headline()->toString(),
            startedAt: ($scanRun->started_at ?? $scanRun->requested_at)->toImmutable(),
            finishedAt: $scanRun->finished_at?->toImmutable(),
            findingCount: $scanRun->findings_count,
        ));

        return new DashboardSnapshot(
            accountCreatedAt: $user->created_at?->toImmutable(),
            metrics: new DashboardMetrics(
                completedScans: $user->scanRuns()->where('status', ScanStatus::Completed->value)->count(),
                activeAssessments: $user->scanRuns()->whereIn('status', [
                    ScanStatus::Requested->value,
                    ScanStatus::Queued->value,
                    ScanStatus::Processing->value,
                ])->count(),
                openFindings: $user->securityFindings()->where('state', SecurityFindingState::Open->value)->count(),
                fileChecks: $user->fileChecks()->count(),
            ),
            recentFindings: $recentFindings,
            scanHistory: $scanHistory,
            activityTimeline: $timeline,
        );
    }
}
