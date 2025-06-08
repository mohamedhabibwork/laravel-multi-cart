<?php

namespace HCart\LaravelMultiCart\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface CartInterface
{
    public function add(Model $cartable, int $quantity = 1, array $attributes = []): self;

    public function update(int $itemId, array $data): self;

    public function remove(int $itemId): bool;

    public function clear(): bool;

    public function count(): int;

    public function total(): float;

    public function subtotal(): float;

    public function tax(): float;

    public function items(): Collection;

    public function get(int $itemId): ?Model;

    public function has(Model $cartable): bool;

    public function quantity(Model $cartable): int;
}
