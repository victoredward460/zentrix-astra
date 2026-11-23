<?php

namespace App\Services\AI;

use App\Data\AgentProviderReply;

interface AgentChatProvider
{
    public function isConfigured(): bool;

    public function model(): ?string;

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function complete(
        array $messages,
        ?int $maxTokens = null,
        bool $disableReasoning = false,
        ?string $modelOverride = null,
    ): AgentProviderReply;
}
