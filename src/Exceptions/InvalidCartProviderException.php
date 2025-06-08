<?php

namespace HCart\LaravelMultiCart\Exceptions;

use Exception;

class InvalidCartProviderException extends Exception
{
    public function __construct(string $provider, int $code = 500)
    {
        $message = "Cart provider {$provider} is not configured";
        parent::__construct($message, $code);
    }
}
