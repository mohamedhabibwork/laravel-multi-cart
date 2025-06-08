<?php

namespace HCart\LaravelMultiCart\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CartCreated
{
    use Dispatchable, SerializesModels;

    public string $cartName;

    public array $cartData;

    public string $provider;

    public ?int $userId;

    public ?string $userType;

    public array $config;

    public function __construct(string $cartName, array $cartData, string $provider = 'session', ?int $userId = null, ?string $userType = null, array $config = [])
    {
        $this->cartName = $cartName;
        $this->cartData = $cartData;
        $this->provider = $provider;
        $this->userId = $userId;
        $this->userType = $userType;
        $this->config = $config;
    }
}
