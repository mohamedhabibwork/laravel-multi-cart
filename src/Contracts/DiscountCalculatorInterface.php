<?php

namespace HCart\LaravelMultiCart\Contracts;

interface DiscountCalculatorInterface
{
    /**
     * Calculate discount for a single item
     */
    public function calculateItemDiscount(float $itemPrice, int $quantity, array $discountSettings, array $itemAttributes = []): float;

    /**
     * Calculate discount for the entire cart
     */
    public function calculateCartDiscount(float $cartSubtotal, array $discountSettings, array $cartItems = []): float;

    /**
     * Calculate tiered discount based on quantity
     */
    public function calculateTieredDiscount(int $quantity, array $discountSettings): float;

    /**
     * Check if minimum amount requirement is met
     */
    public function isMinimumAmountMet(float $amount, array $discountSettings): bool;

    /**
     * Check if discount is included in the price
     */
    public function isDiscountIncluded(array $discountSettings): bool;

    /**
     * Get effective discount rate
     */
    public function getEffectiveDiscountRate(array $discountSettings, float $amount = 0): float;

    /**
     * Validate discount settings
     */
    public function validateDiscountSettings(array $discountSettings): bool;
}
