<?php

namespace App\Exceptions;

use RuntimeException;

class ApiConflictException extends RuntimeException
{
    public function __construct(string $message, public readonly string $codeName = 'CONFLICT', public readonly array $details = [])
    {
        parent::__construct($message);
    }
}
