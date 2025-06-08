<?php

namespace HCart\LaravelMultiCart\Contracts;

interface CartItemInterface
{
    public function getUniqueKey(): string;

    public function getTotal(): float;

    public function getItemAttribute(string $key, $default = null);

    public function setItemAttribute(string $key, $value): void;

    public function hasItemAttribute(string $key): bool;
}
