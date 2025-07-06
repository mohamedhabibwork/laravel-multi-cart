<?php

namespace HCart\LaravelMultiCart\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class ShippingConfiguration
{
    public function __construct(
        public readonly float $cost = 0.0,
        public readonly string $type = 'fixed', // 'fixed', 'percentage', 'per_piece', 'per_weight'
        public readonly bool $included = false,
        public readonly float $weight = 0.0,
        public readonly array $dimensions = [], // ['length' => 0, 'width' => 0, 'height' => 0]
        public readonly ?string $class = null,
        public readonly array $zones = [],
        public readonly int $piecesPerShipping = 1,
        public readonly ?int $maxShippingCharges = null, // null = unlimited
        public readonly float $freeShippingThreshold = 0.0,
        public readonly array $exemptions = [],
        public readonly ?string $description = null
    ) {}

    /**
     * Get shipping settings as array
     */
    public function toArray(): array
    {
        return [
            'cost' => $this->cost,
            'type' => $this->type,
            'included' => $this->included,
            'weight' => $this->weight,
            'dimensions' => $this->dimensions,
            'class' => $this->class,
            'zones' => $this->zones,
            'pieces_per_shipping' => $this->piecesPerShipping,
            'max_shipping_charges' => $this->maxShippingCharges,
            'free_shipping_threshold' => $this->freeShippingThreshold,
            'exemptions' => $this->exemptions,
            'description' => $this->description,
        ];
    }

    /**
     * Create from array
     */
    public static function fromArray(array $data): self
    {
        return new self(
            cost: $data['cost'] ?? 0.0,
            type: $data['type'] ?? 'fixed',
            included: $data['included'] ?? false,
            weight: $data['weight'] ?? 0.0,
            dimensions: $data['dimensions'] ?? [],
            class: $data['class'] ?? null,
            zones: $data['zones'] ?? [],
            piecesPerShipping: $data['pieces_per_shipping'] ?? 1,
            maxShippingCharges: $data['max_shipping_charges'] ?? null,
            freeShippingThreshold: $data['free_shipping_threshold'] ?? 0.0,
            exemptions: $data['exemptions'] ?? [],
            description: $data['description'] ?? null
        );
    }

    /**
     * Calculate piece-based shipping for given quantity
     */
    public function calculatePieceBasedShipping(int $quantity): float
    {
        if ($this->type !== 'per_piece') {
            return $this->cost;
        }

        $charges = (int) ceil($quantity / $this->piecesPerShipping);

        if ($this->maxShippingCharges !== null && $charges > $this->maxShippingCharges) {
            $charges = $this->maxShippingCharges;
        }

        return $charges * $this->cost;
    }
}
