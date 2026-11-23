<?php

namespace App\Console\Commands;

use App\Models\PlatformAuditEvent;
use Illuminate\Console\Command;

class PrunePlatformAuditEvents extends Command
{
    protected $signature = 'platform-audit:prune';

    protected $description = 'Delete platform audit events older than one year';

    public function handle(): int
    {
        $deleted = PlatformAuditEvent::query()
            ->where('created_at', '<', now()->subDays(365))
            ->delete();

        $this->info("Deleted {$deleted} expired platform audit event(s).");

        return self::SUCCESS;
    }
}
