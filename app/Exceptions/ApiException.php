<?php

namespace App\Exceptions;

use RuntimeException;

class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public int $status = 422,
        public array $errors = [],
        public ?string $errorCode = null,
    ) {
        parent::__construct($message);
    }
}
