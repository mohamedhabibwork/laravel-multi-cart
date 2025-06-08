<?php

namespace HCart\LaravelMultiCart\Exceptions;

use Exception;

class CartExistsException extends Exception
{
    public function __construct(string $cartName, ?string $provider = null, int $code = 409)
    {
        $message = "Cart with name {$cartName} already exists";

        if ($provider) {
            $message .= " in provider {$provider}";
        }

        parent::__construct($message, $code);
    }
}
