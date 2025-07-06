<?php

namespace HCart\LaravelMultiCart\Contracts;

interface CartTaxableInterface
{
    /**
     * Get cart-level tax settings
     */
    public function getCartTaxSettings(): array;

    /**
     * Get cart-level tax rate
     */
    public function getCartTaxRate(): float;

    /**
     * Get cart-level tax type (percentage or fixed)
     */
    public function getCartTaxType(): string;

    /**
     * Check if cart-level tax is enabled
     */
    public function isCartTaxEnabled(): bool;

    /**
     * Check if cart-level tax is compound (calculated after discounts)
     */
    public function isCartTaxCompound(): bool;

    /**
     * Get cart-level tax category
     */
    public function getCartTaxCategory(): ?string;

    /**
     * Get cart-level tax exemptions
     */
    public function getCartTaxExemptions(): array;

    /**
     * Check if cart qualifies for tax exemption
     */
    public function isCartTaxExempt(): bool;
}
