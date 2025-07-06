<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\DiscountCalculatorInterface;

class DiscountCalculator implements DiscountCalculatorInterface
{
    /**
     * Calculate discount for a single item
     */
    public function calculateItemDiscount(float $itemPrice, int $quantity, array $discountSettings, array $itemAttributes = []): float
    {
        if (! ($discountSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = $itemPrice * $quantity;

        // Check minimum amount requirement
        if (! $this->isMinimumAmountMet($baseAmount, $discountSettings)) {
            return 0.0;
        }

        $discountValue = $discountSettings['value'] ?? 0.0;
        $discountType = $discountSettings['type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            $discountAmount = $baseAmount * ($discountValue / 100);
        } elseif ($discountType === 'tiered') {
            $discountAmount = $this->calculateTieredDiscount($quantity, $discountSettings);
        } else {
            // Fixed discount
            $discountAmount = $discountValue * $quantity;
        }

        // Apply maximum discount limit
        $maxAmount = $discountSettings['maximum_amount'] ?? null;
        if ($maxAmount !== null && $discountAmount > $maxAmount) {
            $discountAmount = $maxAmount;
        }

        return (float) $discountAmount;
    }

    /**
     * Calculate discount for the entire cart
     */
    public function calculateCartDiscount(float $cartSubtotal, array $discountSettings, array $cartItems = []): float
    {
        if (! ($discountSettings['enabled'] ?? true)) {
            return 0.0;
        }

        // Check minimum amount requirement
        if (! $this->isMinimumAmountMet($cartSubtotal, $discountSettings)) {
            return 0.0;
        }

        $discountValue = $discountSettings['value'] ?? 0.0;
        $discountType = $discountSettings['type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            $discountAmount = $cartSubtotal * ($discountValue / 100);
        } else {
            // Fixed discount
            $discountAmount = $discountValue;
        }

        // Apply maximum discount limit
        $maxAmount = $discountSettings['maximum_amount'] ?? null;
        if ($maxAmount !== null && $discountAmount > $maxAmount) {
            $discountAmount = $maxAmount;
        }

        return (float) $discountAmount;
    }

    /**
     * Calculate tiered discount based on quantity
     */
    public function calculateTieredDiscount(int $quantity, array $discountSettings): float
    {
        $tiers = $discountSettings['tiers'] ?? [];

        if (empty($tiers)) {
            return 0.0;
        }

        // Sort tiers by quantity (descending) to get the best applicable tier
        usort($tiers, function ($a, $b) {
            return ($b['min_quantity'] ?? 0) - ($a['min_quantity'] ?? 0);
        });

        foreach ($tiers as $tier) {
            $minQuantity = $tier['min_quantity'] ?? 0;

            if ($quantity >= $minQuantity) {
                $tierValue = $tier['value'] ?? 0.0;
                $tierType = $tier['type'] ?? 'percentage';

                if ($tierType === 'percentage') {
                    // For tiered percentage, we need the base amount
                    // This should be calculated by the calling method
                    return (float) $tierValue;
                } else {
                    // Fixed discount per item for the quantity
                    return (float) ($tierValue * $quantity);
                }
            }
        }

        return 0.0;
    }

    /**
     * Check if minimum amount requirement is met
     */
    public function isMinimumAmountMet(float $amount, array $discountSettings): bool
    {
        $minimumAmount = $discountSettings['minimum_amount'] ?? null;

        if ($minimumAmount === null) {
            return true;
        }

        return $amount >= $minimumAmount;
    }

    /**
     * Check if discount is included in the price
     */
    public function isDiscountIncluded(array $discountSettings): bool
    {
        return $discountSettings['included'] ?? false;
    }

    /**
     * Get effective discount rate
     */
    public function getEffectiveDiscountRate(array $discountSettings, float $amount = 0): float
    {
        if (! ($discountSettings['enabled'] ?? true)) {
            return 0.0;
        }

        if (! $this->isMinimumAmountMet($amount, $discountSettings)) {
            return 0.0;
        }

        $discountValue = $discountSettings['value'] ?? 0.0;
        $discountType = $discountSettings['type'] ?? 'percentage';

        if ($discountType === 'percentage') {
            return (float) $discountValue;
        }

        // For fixed discount, calculate effective rate based on amount
        if ($amount > 0) {
            return (float) (($discountValue / $amount) * 100);
        }

        return 0.0;
    }

    /**
     * Validate discount settings
     */
    public function validateDiscountSettings(array $discountSettings): bool
    {
        // Check required fields
        if (! isset($discountSettings['type']) || ! in_array($discountSettings['type'], ['percentage', 'fixed', 'tiered'])) {
            return false;
        }

        if (! isset($discountSettings['value']) || ! is_numeric($discountSettings['value'])) {
            return false;
        }

        if (isset($discountSettings['enabled']) && ! is_bool($discountSettings['enabled'])) {
            return false;
        }

        if (isset($discountSettings['included']) && ! is_bool($discountSettings['included'])) {
            return false;
        }

        if (isset($discountSettings['minimum_amount']) && $discountSettings['minimum_amount'] !== null && ! is_numeric($discountSettings['minimum_amount'])) {
            return false;
        }

        if (isset($discountSettings['maximum_amount']) && $discountSettings['maximum_amount'] !== null && ! is_numeric($discountSettings['maximum_amount'])) {
            return false;
        }

        // Validate tiered discount settings
        if ($discountSettings['type'] === 'tiered') {
            if (! isset($discountSettings['tiers']) || ! is_array($discountSettings['tiers'])) {
                return false;
            }

            foreach ($discountSettings['tiers'] as $tier) {
                if (! is_array($tier) || ! isset($tier['min_quantity']) || ! isset($tier['value'])) {
                    return false;
                }

                if (! is_numeric($tier['min_quantity']) || ! is_numeric($tier['value'])) {
                    return false;
                }

                if (isset($tier['type']) && ! in_array($tier['type'], ['percentage', 'fixed'])) {
                    return false;
                }
            }
        }

        return true;
    }
}
