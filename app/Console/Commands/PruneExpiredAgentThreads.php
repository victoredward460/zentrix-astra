<?php

namespace App\Console\Commands;

use App\Models\AgentThread;
use Illuminate\Console\Command;

class PruneExpiredAgentThreads extends Command
{
    protected $signature = 'agent:prune';

    protected $description = 'Delete Central AI Agent conversations older than 30 days';

    public function handle(): int
    {
        $deleted = AgentThread::query()
            ->where('last_activity_at', '<', now()->subDays(30))
            ->delete();

        $this->info("Deleted {$deleted} expired conversation(s).");

        return self::SUCCESS;
    }
}
