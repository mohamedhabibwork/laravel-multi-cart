<?php

namespace HCart\LaravelMultiCart\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class TaxConfiguration
{
    public function __construct(
        public readonly float $rate = 0.0,
        public readonly string $type = 'percentage', // 'percentage' or 'fixed'
        public readonly bool $included = false,
        public readonly bool $compound = false,
        public readonly ?string $category = null,
        public readonly array $exemptions = [],
        public readonly ?string $description = null
    ) {}

    /**
     * Get tax settings as array
     */
    public function toArray(): array
    {
        return [
            'rate' => $this->rate,
            'type' => $this->type,
            'included' => $this->included,
            'compound' => $this->compound,
            'category' => $this->category,
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
            rate: $data['rate'] ?? 0.0,
            type: $data['type'] ?? 'percentage',
            included: $data['included'] ?? false,
            compound: $data['compound'] ?? false,
            category: $data['category'] ?? null,
            exemptions: $data['exemptions'] ?? [],
            description: $data['description'] ?? null
        );
    }
}
