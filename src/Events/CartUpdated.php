<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartUpdated
{
    use Dispatchable, SerializesModels;

    public string $cartName;

    public array $cartData;

    public string $provider;

    public function __construct(string $cartName, array $cartData, string $provider = 'session')
    {
        $this->cartName = $cartName;
        $this->cartData = $cartData;
        $this->provider = $provider;
    }
}
