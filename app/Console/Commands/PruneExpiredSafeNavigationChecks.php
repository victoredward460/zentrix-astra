<?php

namespace App\Console\Commands;

use App\Models\SafeNavigationCheck;
use Illuminate\Console\Command;

class PruneExpiredSafeNavigationChecks extends Command
{
    protected $signature = 'safe-navigation:prune';

    protected $description = 'Delete Safe Navigation history older than 30 days';

    public function handle(): int
    {
        $deleted = SafeNavigationCheck::query()
            ->where('created_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Deleted {$deleted} expired Safe Navigation check(s).");

        return self::SUCCESS;
    }
}
