<?php

namespace App\Services\AI;

use App\Data\AgentProviderReply;
use App\Models\AgentThread;
use App\Models\SecurityFinding;

class AgentConversationService
{
    public function __construct(
        private readonly AgentChatProvider $provider,
        private readonly AgentContextBuilder $contextBuilder,
    ) {}

    public function isConfigured(): bool
    {
        return $this->provider->isConfigured();
    }

    public function model(): ?string
    {
        return $this->provider->model();
    }

    public function reply(
        AgentThread $thread,
        string $question,
        ?SecurityFinding $finding,
        ?int $currentMessageId = null,
        ?string $modelOverride = null,
    ): AgentProviderReply {
        return $this->provider->complete(
            $this->contextBuilder->build($thread, $question, $finding, $currentMessageId),
            modelOverride: $modelOverride,
        );
    }
}
