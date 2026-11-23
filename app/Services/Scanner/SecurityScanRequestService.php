<?php

namespace App\Services\Scanner;

use App\Enums\ScanStatus;
use App\Enums\ScanType;
use App\Jobs\ExecuteSecurityScan;
use App\Models\ScanRun;
use App\Models\SecurityAsset;
use Illuminate\Support\Facades\DB;

class SecurityScanRequestService
{
    public function queue(SecurityAsset $asset, ScanType $scanType = ScanType::SecurityScanner): ScanRun
    {
        return DB::transaction(function () use ($asset, $scanType): ScanRun {
            $scanRun = $asset->scanRuns()->create([
                'user_id' => $asset->user_id,
                'scan_type' => $scanType,
                'status' => ScanStatus::Requested,
                'requested_at' => now(),
            ]);

            $scanRun->forceFill(['status' => ScanStatus::Queued])->save();
            ExecuteSecurityScan::dispatch($scanRun->getKey())->afterCommit();

            return $scanRun;
        });
    }
}
