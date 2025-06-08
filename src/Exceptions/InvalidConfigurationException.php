<?php

namespace HCart\LaravelMultiCart\Exceptions;

use Exception;

class InvalidConfigurationException extends Exception
{
    public function __construct(string $message = 'Invalid cart configuration', int $code = 500)
    {
        parent::__construct($message, $code);
    }
}
