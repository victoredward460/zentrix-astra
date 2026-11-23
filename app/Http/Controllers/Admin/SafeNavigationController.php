<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitSafeNavigationCheck;
use App\Models\SafeNavigationCheck;
use App\Services\Admin\PlatformAuditLogger;
use App\Services\SafeNavigation\ProviderStatusService;
use App\Services\SafeNavigation\SafeNavigationAssessmentService;
use App\Services\SafeNavigation\UrlNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SafeNavigationController extends Controller
{
    public function index(
        Request $request,
        ProviderStatusService $providerStatusService,
        SafeNavigationAssessmentService $assessmentService,
        PlatformAuditLogger $auditLogger,
    ): View {
        $auditLogger->record($request->user(), 'admin.safe_navigation_history_viewed');
        $checks = $request->user()->safeNavigationChecks()->latest()->paginate(10);
        $checks->getCollection()->each(function (SafeNavigationCheck $check) use ($assessmentService): void {
            $check->setAttribute('display_classification', $assessmentService->classificationForDisplay($check));
        });

        return view('admin.safe-navigation', [
            'providers' => $providerStatusService->all(),
            'checks' => $checks,
        ]);
    }

    public function store(
        SubmitSafeNavigationCheck $request,
        UrlNormalizer $normalizer,
        SafeNavigationAssessmentService $assessmentService,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $url = $normalizer->normalize($request->validated('url'));
        $check = $assessmentService->assess($request->user(), $url);
        $auditLogger->record($request->user(), 'admin.safe_navigation_assessed', $check);

        return redirect()
            ->route('safe-navigation.checks.show', $check)
            ->with('status', 'URL assessment recorded. Provider results and availability are shown in the report; the destination itself was not opened.');
    }

    public function show(
        Request $request,
        SafeNavigationCheck $safeNavigationCheck,
        SafeNavigationAssessmentService $assessmentService,
        PlatformAuditLogger $auditLogger,
    ): View {
        Gate::authorize('view', $safeNavigationCheck);
        $auditLogger->record($request->user(), 'admin.safe_navigation_report_viewed', $safeNavigationCheck);

        return view('admin.safe-navigation-show', [
            'check' => $safeNavigationCheck,
            'classification' => $assessmentService->classificationForDisplay($safeNavigationCheck),
            'providerResults' => $assessmentService->resultsForDisplay($safeNavigationCheck),
        ]);
    }
}
