<?php

namespace App\Jobs;

use App\Enums\ScanStatus;
use App\Models\ScanRun;
use App\Services\Scanner\SecurityScanService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExecuteSecurityScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 35;

    public function __construct(public readonly int $scanRunId) {}

    public function handle(SecurityScanService $scanService): void
    {
        $scanService->execute($this->scanRunId);
    }

    public function failed(Throwable $exception): void
    {
        ScanRun::query()
            ->whereKey($this->scanRunId)
            ->whereIn('status', [ScanStatus::Queued->value, ScanStatus::Processing->value])
            ->update([
                'status' => ScanStatus::Failed->value,
                'failure_code' => 'scan_execution_failed',
                'finished_at' => now(),
                'updated_at' => now(),
            ]);

        Log::warning('Security scan job failed.', [
            'scan_run_id' => $this->scanRunId,
            'exception' => $exception::class,
        ]);
    }
}
