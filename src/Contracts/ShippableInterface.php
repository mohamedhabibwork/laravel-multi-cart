<?php

namespace HCart\LaravelMultiCart\Contracts;

interface ShippableInterface
{
    /**
     * Get shipping settings for this item
     */
    public function getShippingSettings(): array;

    /**
     * Get shipping cost for this item
     */
    public function getShippingCost(): float;

    /**
     * Get shipping type for this item (percentage, fixed, per_piece, per_weight)
     */
    public function getShippingType(): string;

    /**
     * Check if shipping is included in the price
     */
    public function isShippingIncluded(): bool;

    /**
     * Get shipping weight for this item
     */
    public function getShippingWeight(): float;

    /**
     * Get shipping dimensions for this item
     *
     * @return array ['length' => float, 'width' => float, 'height' => float]
     */
    public function getShippingDimensions(): array;

    /**
     * Get shipping class for this item
     */
    public function getShippingClass(): ?string;

    /**
     * Get shipping zone restrictions for this item
     */
    public function getShippingZones(): array;

    /**
     * Get piece-based shipping configuration
     * e.g., ['pieces_per_charge' => 2, 'charge_per_group' => 5.00, 'max_charges' => null]
     */
    public function getPieceBasedShippingConfig(): array;

    /**
     * Get pieces per shipping charge
     */
    public function getPiecesPerShipping(): int;

    /**
     * Get maximum shipping charges (null for unlimited)
     */
    public function getMaxShippingCharges(): ?int;

    /**
     * Check if this item qualifies for free shipping
     */
    public function qualifiesForFreeShipping(float $cartTotal): bool;
}
