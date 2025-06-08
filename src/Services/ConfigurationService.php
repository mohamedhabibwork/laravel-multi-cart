<?php

namespace HCart\LaravelMultiCart\Services;

use HCart\LaravelMultiCart\Contracts\CartConfigInterface;

class ConfigurationService
{
    protected CartConfigInterface $config;

    public function __construct(CartConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Get the cart model class
     */
    public function getCartModel(): string
    {
        return $this->config->getCartModel();
    }

    /**
     * Get the cart item model class
     */
    public function getCartItemModel(): string
    {
        return $this->config->getCartItemModel();
    }

    /**
     * Get the unique item callback
     */
    public function getUniqueItemCallback(): ?callable
    {
        return $this->config->getUniqueItemCallback();
    }

    /**
     * Get the tax rate
     */
    public function getTaxRate(): float
    {
        return $this->config->getTaxRate();
    }

    /**
     * Get the currency
     */
    public function getCurrency(): string
    {
        return $this->config->getCurrency();
    }

    /**
     * Get the default provider
     */
    public function getDefaultProvider(): string
    {
        return $this->config->getDefaultProvider();
    }

    /**
     * Check if duplicates should be prevented
     */
    public function shouldPreventDuplicates(): bool
    {
        return $this->config->shouldPreventDuplicates();
    }

    /**
     * Get the item update callback
     */
    public function getItemUpdateCallback(): ?callable
    {
        return $this->config->getItemUpdateCallback();
    }

    /**
     * Get the item remove callback
     */
    public function getItemRemoveCallback(): ?callable
    {
        return $this->config->getItemRemoveCallback();
    }

    /**
     * Set configuration values
     */
    public function setConfig(array|CartConfigInterface $config): void
    {
        if ($config instanceof CartConfigInterface) {
            $this->config = $config;
        } else {
            $this->config->setConfig($config);
        }
    }

    /**
     * Get configuration value
     */
    public function getConfig(?string $key = null)
    {
        if ($key === null) {
            return $this->config;  // Return the config instance when no key specified
        }

        return $this->config->getConfig($key);
    }

    /**
     * Get the underlying config instance
     */
    public function getConfigInstance(): CartConfigInterface
    {
        return $this->config;
    }
}
