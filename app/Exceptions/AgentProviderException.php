<?php

namespace App\Exceptions;

use App\Enums\AgentProviderFailure;
use RuntimeException;

class AgentProviderException extends RuntimeException
{
    public function __construct(
        public readonly AgentProviderFailure $failure,
        public readonly ?int $retryAfterSeconds = null,
    )
    {
        parent::__construct($failure->value);
    }

    public function userMessage(): string
    {
        return $this->failure->userMessage($this->retryAfterSeconds);
    }
}
