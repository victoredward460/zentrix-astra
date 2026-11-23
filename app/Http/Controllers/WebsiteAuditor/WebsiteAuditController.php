<?php

namespace App\Http\Controllers\WebsiteAuditor;

use App\Enums\AssetVerificationStatus;
use App\Enums\ScanType;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestWebsiteAudit;
use App\Models\ScanRun;
use App\Models\SecurityAsset;
use App\Services\Scanner\SecurityScanRequestService;
use App\Services\WebsiteAuditor\WebsiteAuditReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WebsiteAuditController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('website-auditor.index', [
            'assets' => $user->securityAssets()
                ->where('verification_status', AssetVerificationStatus::Verified->value)
                ->orderBy('hostname')
                ->get(),
            'audits' => $user->scanRuns()
                ->where('scan_type', ScanType::WebsiteAudit->value)
                ->with('securityAsset')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(
        RequestWebsiteAudit $request,
        SecurityAsset $securityAsset,
        SecurityScanRequestService $requestService,
    ): RedirectResponse {
        Gate::authorize('scan', $securityAsset);
        $scanRun = $requestService->queue($securityAsset, ScanType::WebsiteAudit);

        return redirect()
            ->route('website-auditor.runs.show', $scanRun)
            ->with('status', 'Your website assessment has been queued.');
    }

    public function show(ScanRun $scanRun, WebsiteAuditReportService $reportService): View
    {
        $this->authorize('view', $scanRun);
        abort_unless($scanRun->scan_type === ScanType::WebsiteAudit, 404);
        $scanRun->load(['securityAsset', 'checkResults', 'findings']);

        return view('website-auditor.show', [
            'scanRun' => $scanRun,
            'report' => $reportService->summarize($scanRun),
        ]);
    }
}
