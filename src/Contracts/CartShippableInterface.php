<?php

namespace HCart\LaravelMultiCart\Contracts;

interface CartShippableInterface
{
    /**
     * Get cart-level shipping settings
     */
    public function getCartShippingSettings(): array;

    /**
     * Get cart-level shipping cost
     */
    public function getCartShippingCost(): float;

    /**
     * Get cart-level shipping type (percentage, fixed, per_piece, per_weight)
     */
    public function getCartShippingType(): string;

    /**
     * Check if cart-level shipping is enabled
     */
    public function isCartShippingEnabled(): bool;

    /**
     * Get cart-level shipping weight
     */
    public function getCartShippingWeight(): float;

    /**
     * Get cart-level shipping dimensions
     *
     * @return array ['length' => float, 'width' => float, 'height' => float]
     */
    public function getCartShippingDimensions(): array;

    /**
     * Get cart-level shipping class
     */
    public function getCartShippingClass(): ?string;

    /**
     * Get cart-level shipping zones
     */
    public function getCartShippingZones(): array;

    /**
     * Get cart-level piece-based shipping configuration
     * e.g., ['pieces_per_charge' => 2, 'charge_per_group' => 5.00, 'max_charges' => null]
     */
    public function getCartPieceBasedShippingConfig(): array;

    /**
     * Get cart-level pieces per shipping charge
     */
    public function getCartPiecesPerShipping(): int;

    /**
     * Get cart-level maximum shipping charges (null for unlimited)
     */
    public function getCartMaxShippingCharges(): ?int;

    /**
     * Check if cart qualifies for free shipping
     */
    public function cartQualifiesForFreeShipping(float $cartTotal): bool;

    /**
     * Get cart-level shipping exemptions
     */
    public function getCartShippingExemptions(): array;

    /**
     * Check if cart is shipping exempt
     */
    public function isCartShippingExempt(): bool;
}
