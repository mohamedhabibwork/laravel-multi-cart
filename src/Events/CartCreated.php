<?php

namespace HCart\LaravelMultiCart\Events;

use HCart\LaravelMultiCart\Enums\CartProvider;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $cartName,
        public array $cartData,
        public string|CartProvider $provider = 'session',
        public ?int $userId = null,
        public ?string $userType = null,
        public array $config = []
    ) {}
}
