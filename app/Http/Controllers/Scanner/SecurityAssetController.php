<?php

namespace App\Http\Controllers\Scanner;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterSecurityAssetRequest;
use App\Models\SecurityAsset;
use App\Services\Scanner\AssetRegistrationService;
use App\Services\Scanner\DomainOwnershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SecurityAssetController extends Controller
{
    public function index(Request $request): View
    {
        return view('scanner.index', [
            'assets' => $request->user()->securityAssets()->latest()->paginate(10),
            'scanRuns' => $request->user()->scanRuns()
                ->where('scan_type', 'security_scanner')
                ->with('securityAsset')
                ->latest()
                ->paginate(10, ['*'], 'history'),
        ]);
    }

    public function store(RegisterSecurityAssetRequest $request, AssetRegistrationService $registrationService): RedirectResponse
    {
        $challenge = $registrationService->register($request->user(), $request->validated('hostname'));

        return redirect()
            ->route('scanner.assets.index')
            ->with('ownership_challenge', [
                'hostname' => $challenge->asset->hostname,
                'record_name' => $challenge->recordName(),
                'record_value' => $challenge->recordValue(),
            ])
            ->with('status', 'Add the DNS TXT record shown below, then verify the hostname.');
    }

    public function verify(
        Request $request,
        SecurityAsset $securityAsset,
        DomainOwnershipService $ownershipService,
    ): RedirectResponse {
        $this->authorize('verify', $securityAsset);

        try {
            $ownershipService->verify($securityAsset);
        } catch (\App\Services\Scanner\Exceptions\OwnershipVerificationFailed $exception) {
            return back()->withErrors(['verification' => $exception->getMessage()]);
        } catch (\App\Services\Scanner\Exceptions\DnsLookupFailed $exception) {
            Log::notice('Asset ownership DNS check failed.', [
                'security_asset_id' => $securityAsset->getKey(),
                'exception' => $exception::class,
            ]);

            return back()->withErrors(['verification' => 'DNS could not be checked. Try again shortly.']);
        }

        return back()->with('status', 'Hostname ownership verified. You can now request a passive scan.');
    }

    public function refreshChallenge(
        Request $request,
        SecurityAsset $securityAsset,
        DomainOwnershipService $ownershipService,
    ): RedirectResponse {
        $this->authorize('verify', $securityAsset);

        try {
            $challenge = $ownershipService->issue($securityAsset);
        } catch (\App\Services\Scanner\Exceptions\OwnershipVerificationFailed $exception) {
            return back()->withErrors(['verification' => $exception->getMessage()]);
        }

        return back()
            ->with('ownership_challenge', [
                'hostname' => $challenge->asset->hostname,
                'record_name' => $challenge->recordName(),
                'record_value' => $challenge->recordValue(),
            ])
            ->with('status', 'A fresh DNS ownership challenge was generated.');
    }
}
