<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\ShippingCalculatorInterface;

class ShippingCalculator implements ShippingCalculatorInterface
{
    /**
     * Calculate shipping for a single item
     */
    public function calculateItemShipping(float $itemPrice, int $quantity, array $shippingSettings, array $itemAttributes = []): float
    {
        if (! ($shippingSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $shippingValue = $shippingSettings['value'] ?? 0.0;
        $shippingType = $shippingSettings['type'] ?? 'fixed';

        if ($shippingType === 'percentage') {
            $shippingAmount = ($itemPrice * $quantity) * ($shippingValue / 100);
        } elseif ($shippingType === 'per_piece') {
            $shippingAmount = $this->calculatePieceBasedShipping($quantity, $shippingSettings);
        } else {
            // Fixed shipping
            $shippingAmount = $shippingValue * $quantity;
        }

        return (float) $shippingAmount;
    }

    /**
     * Calculate shipping for the entire cart
     */
    public function calculateCartShipping(float $cartSubtotal, array $shippingSettings, array $cartItems = []): float
    {
        if (! ($shippingSettings['enabled'] ?? true)) {
            return 0.0;
        }

        // Check for free shipping threshold
        if ($this->isFreeShippingEligible($cartSubtotal, $shippingSettings)) {
            return 0.0;
        }

        $shippingValue = $shippingSettings['value'] ?? 0.0;
        $shippingType = $shippingSettings['type'] ?? 'fixed';

        if ($shippingType === 'percentage') {
            $shippingAmount = $cartSubtotal * ($shippingValue / 100);
        } elseif ($shippingType === 'per_piece') {
            $totalQuantity = array_sum(array_column($cartItems, 'quantity'));
            $shippingAmount = $this->calculatePieceBasedShipping($totalQuantity, $shippingSettings);
        } elseif ($shippingType === 'weight_based') {
            $totalWeight = $this->calculateTotalWeight($cartItems);
            $shippingAmount = $this->calculateWeightBasedShipping($totalWeight, $shippingSettings);
        } else {
            // Fixed shipping
            $shippingAmount = $shippingValue;
        }

        return (float) $shippingAmount;
    }

    /**
     * Calculate shipping based on quantity/pieces
     */
    public function calculatePieceBasedShipping(int $totalQuantity, array $shippingSettings): float
    {
        $shippingValue = $shippingSettings['value'] ?? 0.0;
        $piecesPerShipping = $shippingSettings['pieces_per_shipping'] ?? 1;
        $maxShippingCharges = $shippingSettings['max_shipping_charges'] ?? null; // null means unlimited

        if ($piecesPerShipping <= 0) {
            return 0.0;
        }

        // Calculate how many shipping charges apply
        $shippingCharges = ceil($totalQuantity / $piecesPerShipping);

        // Apply maximum shipping charges limit if set
        if ($maxShippingCharges !== null && $shippingCharges > $maxShippingCharges) {
            $shippingCharges = $maxShippingCharges;
        }

        return (float) ($shippingCharges * $shippingValue);
    }

    /**
     * Calculate shipping based on weight
     */
    public function calculateWeightBasedShipping(float $totalWeight, array $shippingSettings): float
    {
        if (! ($shippingSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseRate = $shippingSettings['base_rate'] ?? 0.0;
        $weightRate = $shippingSettings['weight_rate'] ?? 0.0;
        $freeWeightThreshold = $shippingSettings['free_weight_threshold'] ?? null;

        if ($freeWeightThreshold !== null && $totalWeight <= $freeWeightThreshold) {
            return 0.0;
        }

        return (float) ($baseRate + ($totalWeight * $weightRate));
    }

    /**
     * Check if free shipping threshold is met
     */
    public function isFreeShippingEligible(float $cartSubtotal, array $shippingSettings): bool
    {
        $freeShippingThreshold = $shippingSettings['free_shipping_threshold'] ?? null;

        if ($freeShippingThreshold === null) {
            return false;
        }

        return $cartSubtotal >= $freeShippingThreshold;
    }

    /**
     * Check if shipping is included in the price
     */
    public function isShippingIncluded(array $shippingSettings): bool
    {
        return $shippingSettings['included'] ?? false;
    }

    /**
     * Get effective shipping rate
     */
    public function getEffectiveShippingRate(array $shippingSettings, int $quantity = 1): float
    {
        if (! ($shippingSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $shippingValue = $shippingSettings['value'] ?? 0.0;
        $shippingType = $shippingSettings['type'] ?? 'fixed';

        if ($shippingType === 'percentage') {
            return (float) $shippingValue;
        } elseif ($shippingType === 'per_piece') {
            $piecesPerShipping = $shippingSettings['pieces_per_shipping'] ?? 1;

            return (float) ($shippingValue / $piecesPerShipping);
        } else {
            return (float) $shippingValue;
        }
    }

    /**
     * Validate shipping settings
     */
    public function validateShippingSettings(array $shippingSettings): bool
    {
        // Check required fields
        if (! isset($shippingSettings['type']) || ! in_array($shippingSettings['type'], ['percentage', 'fixed', 'per_piece', 'weight_based'])) {
            return false;
        }

        if (! isset($shippingSettings['value']) || ! is_numeric($shippingSettings['value'])) {
            return false;
        }

        if (isset($shippingSettings['enabled']) && ! is_bool($shippingSettings['enabled'])) {
            return false;
        }

        if (isset($shippingSettings['included']) && ! is_bool($shippingSettings['included'])) {
            return false;
        }

        if (isset($shippingSettings['pieces_per_shipping']) && (! is_numeric($shippingSettings['pieces_per_shipping']) || $shippingSettings['pieces_per_shipping'] <= 0)) {
            return false;
        }

        if (isset($shippingSettings['max_shipping_charges']) && $shippingSettings['max_shipping_charges'] !== null && (! is_numeric($shippingSettings['max_shipping_charges']) || $shippingSettings['max_shipping_charges'] <= 0)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate total weight from cart items
     */
    protected function calculateTotalWeight(array $cartItems): float
    {
        $totalWeight = 0.0;

        foreach ($cartItems as $item) {
            $itemWeight = $item['attributes']['weight'] ?? 0.0;
            $quantity = $item['quantity'] ?? 1;
            $totalWeight += $itemWeight * $quantity;
        }

        return (float) $totalWeight;
    }
}
