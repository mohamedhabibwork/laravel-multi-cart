<?php

namespace HCart\LaravelMultiCart\Exceptions;

use Exception;

class CartItemNotFoundException extends Exception
{
    public function __construct(string $itemId, int $code = 404)
    {
        $message = "Cart item with ID {$itemId} not found";
        parent::__construct($message, $code);
    }
}
