<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Models\ScanRun;
use App\Models\SecurityAsset;
use App\Services\Scanner\SecurityScanRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScanRunController extends Controller
{
    public function store(SecurityAsset $securityAsset, SecurityScanRequestService $requestService): RedirectResponse
    {
        Gate::authorize('scan', $securityAsset);
        $scanRun = $requestService->queue($securityAsset);

        return redirect()
            ->route('scanner.runs.show', $scanRun)
            ->with('status', 'Your passive assessment has been queued.');
    }

    public function show(ScanRun $scanRun): View
    {
        $this->authorize('view', $scanRun);
        $scanRun->load(['securityAsset', 'checkResults', 'findings']);

        return view('scanner.show', ['scanRun' => $scanRun]);
    }
}
