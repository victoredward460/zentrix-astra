<?php

namespace App\Data;

final readonly class AgentProviderReply
{
    public function __construct(
        public string $content,
        public string $provider,
        public string $model,
        public ?int $promptTokens,
        public ?int $completionTokens,
    ) {}
}
