<?php

namespace App\Services\SafeNavigation;

use RuntimeException;

class UrlReputationProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $reason = 'provider_error',
        public readonly ?int $retryAfterSeconds = null,
    )
    {
        parent::__construct('The reputation provider lookup could not be completed.');
    }
}
