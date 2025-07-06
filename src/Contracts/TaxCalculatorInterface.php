<?php

namespace HCart\LaravelMultiCart\Contracts;

use HCart\LaravelMultiCart\Models\CartItem;
use Illuminate\Support\Collection;

interface TaxCalculatorInterface
{
    /**
     * Calculate tax for a single cart item
     *
     * @param  array  $item  Cart item data
     * @param  array  $cartData  Complete cart data for context
     */
    public function calculateItemTax(array $item, array $cartData): float;

    /**
     * Calculate total tax for all cart items
     *
     * @param  Collection  $items  Cart items collection
     * @param  array  $cartData  Complete cart data for context
     */
    public function calculateTotalTax(Collection $items, array $cartData): float;

    /**
     * Calculate tax for a specific tax category
     *
     * @param  string  $category  Tax category
     * @param  Collection  $items  Cart items collection
     * @param  array  $cartData  Complete cart data for context
     */
    public function calculateCategoryTax(string $category, Collection $items, array $cartData): float;

    /**
     * Get tax breakdown by category
     *
     * @param  Collection  $items  Cart items collection
     * @param  array  $cartData  Complete cart data for context
     */
    public function getTaxBreakdown(Collection $items, array $cartData): array;

    /**
     * Check if tax calculation should be compound (after discount)
     *
     * @param  array  $item  Cart item data
     * @param  array  $cartData  Complete cart data for context
     */
    public function isCompoundTax(array $item, array $cartData): bool;

    /**
     * Get applicable tax rate for an item
     *
     * @param  array  $item  Cart item data
     * @param  array  $cartData  Complete cart data for context
     */
    public function getApplicableTaxRate(array $item, array $cartData): float;

    /**
     * Calculate compound tax (tax on discounted amount)
     */
    public function calculateCompoundTax(CartItem $item, float $discountAmount, array $taxSettings = []): float;

    /**
     * Check if tax should be applied to an item
     */
    public function shouldApplyTax(CartItem $item, array $taxSettings = []): bool;

    /**
     * Get effective tax rate for an item
     */
    public function getEffectiveTaxRate(CartItem $item, array $taxSettings = []): float;

    /**
     * Calculate tax with exemptions
     */
    public function calculateTaxWithExemptions(CartItem $item, array $exemptions = []): float;
}
