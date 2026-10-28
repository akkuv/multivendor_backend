<?php

namespace App\Exceptions;

use RuntimeException;

class ShiprocketApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $endpoint = '',
        public readonly ?array $response = null,
        public readonly bool $retryable = true,
    ) {
        parent::__construct($message);
    }
}
