<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformAuditLogger;
use App\Services\Admin\PlatformFeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlatformFeatureController extends Controller
{
    public function index(PlatformFeatureService $features): View
    {
        return view('admin.features.index', [
            'features' => $features->all(),
        ]);
    }

    public function update(
        Request $request,
        string $feature,
        PlatformFeatureService $features,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        abort_unless($features->supports($feature), 404);

        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);
        $enabled = (bool) $validated['enabled'];

        if ($feature === 'public-registration' && $enabled && ! config('auth.allow_registration')) {
            return back()->withErrors([
                'enabled' => 'Public registration is locked off by the deployment environment.',
            ]);
        }

        $wasEnabled = $features->isEnabled($feature);

        if ($wasEnabled !== $enabled) {
            DB::transaction(function () use ($features, $auditLogger, $request, $feature, $enabled): void {
                $features->set($feature, $enabled, (int) $request->user()->getKey());
                $auditLogger->record($request->user(), 'admin.feature_access_changed', metadata: [
                    'feature_key' => $feature,
                    'new_enabled' => $enabled,
                ]);
            });
        }

        return redirect()
            ->route('admin.features.index')
            ->with('status', $enabled
                ? 'Feature access enabled for ordinary users.'
                : 'Feature access disabled for ordinary users. Administrator controls remain available.');
    }
}
