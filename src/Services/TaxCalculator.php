<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\TaxCalculatorInterface;
use HCart\LaravelMultiCart\Models\CartItem;
use Illuminate\Support\Collection;

class TaxCalculator implements TaxCalculatorInterface
{
    /**
     * Calculate tax for a single cart item
     */
    public function calculateItemTax(array $item, array $cartData): float
    {
        $taxSettings = $item['tax_settings'] ?? [];

        if (! ($taxSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = ($item['price'] ?? 0.0) * ($item['quantity'] ?? 1);
        $taxValue = $taxSettings['value'] ?? 0.0;
        $taxType = $taxSettings['type'] ?? 'percentage';

        if ($taxType === 'percentage') {
            $taxAmount = $baseAmount * ($taxValue / 100);
        } else {
            $taxAmount = $taxValue * ($item['quantity'] ?? 1);
        }

        return (float) $taxAmount;
    }

    /**
     * Calculate total tax for all cart items
     */
    public function calculateTotalTax(Collection $items, array $cartData): float
    {
        $totalTax = 0.0;

        foreach ($items as $item) {
            $totalTax += $this->calculateItemTax($item, $cartData);
        }

        return (float) $totalTax;
    }

    /**
     * Check if tax is included in the price
     */
    public function isTaxIncluded(array $taxSettings): bool
    {
        return $taxSettings['included'] ?? false;
    }

    /**
     * Calculate tax for a specific tax category
     */
    public function calculateCategoryTax(string $category, Collection $items, array $cartData): float
    {
        $categoryTax = 0.0;

        foreach ($items as $item) {
            $taxSettings = $item['tax_settings'] ?? [];
            if (($taxSettings['category'] ?? 'standard') === $category) {
                $categoryTax += $this->calculateItemTax($item, $cartData);
            }
        }

        return (float) $categoryTax;
    }

    /**
     * Get tax breakdown by category
     */
    public function getTaxBreakdown(Collection $items, array $cartData): array
    {
        $breakdown = [];

        foreach ($items as $item) {
            $taxSettings = $item['tax_settings'] ?? [];
            $category = $taxSettings['category'] ?? 'standard';

            if (! isset($breakdown[$category])) {
                $breakdown[$category] = 0.0;
            }

            $breakdown[$category] += $this->calculateItemTax($item, $cartData);
        }

        return $breakdown;
    }

    /**
     * Check if tax calculation should be compound (after discount)
     */
    public function isCompoundTax(array $item, array $cartData): bool
    {
        $taxSettings = $item['tax_settings'] ?? [];

        return $taxSettings['compound'] ?? false;
    }

    /**
     * Get applicable tax rate for an item
     */
    public function getApplicableTaxRate(array $item, array $cartData): float
    {
        $taxSettings = $item['tax_settings'] ?? [];

        if (! ($taxSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $taxValue = $taxSettings['value'] ?? 0.0;
        $taxType = $taxSettings['type'] ?? 'percentage';

        if ($taxType === 'percentage') {
            return (float) $taxValue;
        }

        return 0.0; // For fixed tax, return 0 as rate
    }

    /**
     * Calculate compound tax (tax on discounted amount)
     */
    public function calculateCompoundTax(CartItem $item, float $discountAmount, array $taxSettings = []): float
    {
        if (empty($taxSettings)) {
            $taxSettings = $item->tax_settings ?? [];
        }

        if (! ($taxSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $baseAmount = $item->price * $item->quantity;
        $taxableAmount = $baseAmount - $discountAmount;
        $taxValue = $taxSettings['value'] ?? 0.0;
        $taxType = $taxSettings['type'] ?? 'percentage';

        if ($taxType === 'percentage') {
            $taxAmount = $taxableAmount * ($taxValue / 100);
        } else {
            $taxAmount = $taxValue;
        }

        return (float) max(0, $taxAmount);
    }

    /**
     * Check if tax should be applied to an item
     */
    public function shouldApplyTax(CartItem $item, array $taxSettings = []): bool
    {
        if (empty($taxSettings)) {
            $taxSettings = $item->tax_settings ?? [];
        }

        return $taxSettings['enabled'] ?? true;
    }

    /**
     * Get effective tax rate for an item
     */
    public function getEffectiveTaxRate(CartItem $item, array $taxSettings = []): float
    {
        if (empty($taxSettings)) {
            $taxSettings = $item->tax_settings ?? [];
        }

        if (! ($taxSettings['enabled'] ?? true)) {
            return 0.0;
        }

        $taxValue = $taxSettings['value'] ?? 0.0;
        $taxType = $taxSettings['type'] ?? 'percentage';

        if ($taxType === 'percentage') {
            return (float) $taxValue;
        }

        return 0.0; // For fixed tax, return 0 as rate
    }

    /**
     * Calculate tax with exemptions
     */
    public function calculateTaxWithExemptions(CartItem $item, array $exemptions = []): float
    {
        $taxSettings = $item->tax_settings ?? [];

        if (! ($taxSettings['enabled'] ?? true)) {
            return 0.0;
        }

        // Check if item is exempt
        $category = $taxSettings['category'] ?? 'standard';
        if (in_array($category, $exemptions)) {
            return 0.0;
        }

        return $this->calculateItemTax([
            'price' => $item->price,
            'quantity' => $item->quantity,
            'tax_settings' => $taxSettings,
        ], []);
    }

    /**
     * Validate tax settings
     */
    public function validateTaxSettings(array $taxSettings): bool
    {
        // Check required fields
        if (! isset($taxSettings['type']) || ! in_array($taxSettings['type'], ['percentage', 'fixed'])) {
            return false;
        }

        if (! isset($taxSettings['value']) || ! is_numeric($taxSettings['value'])) {
            return false;
        }

        if (isset($taxSettings['enabled']) && ! is_bool($taxSettings['enabled'])) {
            return false;
        }

        if (isset($taxSettings['included']) && ! is_bool($taxSettings['included'])) {
            return false;
        }

        if (isset($taxSettings['compound']) && ! is_bool($taxSettings['compound'])) {
            return false;
        }

        return true;
    }
}
