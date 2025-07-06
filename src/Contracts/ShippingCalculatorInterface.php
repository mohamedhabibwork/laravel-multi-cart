<?php

namespace HCart\LaravelMultiCart\Contracts;

interface ShippingCalculatorInterface
{
    /**
     * Calculate shipping for a single item
     */
    public function calculateItemShipping(float $itemPrice, int $quantity, array $shippingSettings, array $itemAttributes = []): float;

    /**
     * Calculate shipping for the entire cart
     */
    public function calculateCartShipping(float $cartSubtotal, array $shippingSettings, array $cartItems = []): float;

    /**
     * Calculate shipping based on quantity/pieces
     */
    public function calculatePieceBasedShipping(int $totalQuantity, array $shippingSettings): float;

    /**
     * Calculate shipping based on weight
     */
    public function calculateWeightBasedShipping(float $totalWeight, array $shippingSettings): float;

    /**
     * Check if free shipping threshold is met
     */
    public function isFreeShippingEligible(float $cartSubtotal, array $shippingSettings): bool;

    /**
     * Check if shipping is included in the price
     */
    public function isShippingIncluded(array $shippingSettings): bool;

    /**
     * Get effective shipping rate
     */
    public function getEffectiveShippingRate(array $shippingSettings, int $quantity = 1): float;

    /**
     * Validate shipping settings
     */
    public function validateShippingSettings(array $shippingSettings): bool;
}
