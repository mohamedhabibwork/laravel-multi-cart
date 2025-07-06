<?php

namespace HCart\LaravelMultiCart\Contracts;

interface CartProviderInterface
{
    public function get(string $cartName): ?array;

    public function put(string $cartName, array $data, ?int $ttl = null): bool;

    public function forget(string $cartName): bool;

    public function flush(): bool;

    public function exists(string $cartName): bool;

    public function getAllNames(): array;
}
