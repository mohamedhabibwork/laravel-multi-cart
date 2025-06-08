<?php

namespace HCart\LaravelMultiCart\Contracts;

interface CartConfigInterface
{
    public function getCartModel(): string;

    public function getCartItemModel(): string;

    public function getUniqueItemCallback(): ?callable;

    public function getTaxRate(): float;

    public function getCurrency(): string;

    public function getDefaultProvider(): string;

    public function shouldPreventDuplicates(): bool;

    public function getItemUpdateCallback(): ?callable;

    public function getItemRemoveCallback(): ?callable;

    public function setConfig(array $config): self;

    public function getConfig(?string $key = null);
}
