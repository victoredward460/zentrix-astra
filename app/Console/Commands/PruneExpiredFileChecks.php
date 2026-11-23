<?php

namespace App\Console\Commands;

use App\Models\FileCheck;
use App\Services\FileCheck\FileCheckService;
use Illuminate\Console\Command;

class PruneExpiredFileChecks extends Command
{
    protected $signature = 'file-checks:prune';

    protected $description = 'Delete private uploads and records older than the retention period';

    public function handle(FileCheckService $fileCheckService): int
    {
        $expired = FileCheck::query()
            ->where('created_at', '<', now()->subDays(FileCheckService::RETENTION_DAYS))
            ->orderBy('id');
        $deleted = 0;

        $expired->chunkById(100, function ($fileChecks) use ($fileCheckService, &$deleted): void {
            foreach ($fileChecks as $fileCheck) {
                $fileCheckService->delete($fileCheck);
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} expired file check(s).");

        return self::SUCCESS;
    }
}
