<?php

namespace App\Exceptions;

use RuntimeException;

class FaceProcessingException extends RuntimeException
{
    public function __construct(string $message, public readonly string $codeName)
    {
        parent::__construct($message);
    }
}
