<?php

namespace HCart\LaravelMultiCart\Contracts;

interface TaxableInterface
{
    /**
     * Get tax settings for this item
     */
    public function getTaxSettings(): array;

    /**
     * Get tax rate for this item
     */
    public function getTaxRate(): float;

    /**
     * Get tax type for this item (percentage or fixed)
     */
    public function getTaxType(): string;

    /**
     * Check if tax is included in the price
     */
    public function isTaxIncluded(): bool;

    /**
     * Check if tax should be calculated as compound (after discount)
     */
    public function isCompoundTax(): bool;

    /**
     * Get tax category for this item
     */
    public function getTaxCategory(): ?string;
}
