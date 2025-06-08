<?php

namespace HCart\LaravelMultiCart\Exceptions;

use Exception;

class CartNotFoundException extends Exception
{
    public function __construct(string $cartName, ?string $provider = null)
    {
        $message = "Cart [{$cartName}] not found";

        if ($provider) {
            $message .= " in provider [{$provider}]";
        }

        parent::__construct($message);
    }
}
