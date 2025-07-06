<?php

namespace HCart\LaravelMultiCart\Enums;

enum CartProvider: string
{
    case SESSION = 'session';
    case CACHE = 'cache';
    case DATABASE = 'database';
    case REDIS = 'redis';
    case FILE = 'file';

    /**
     * Get all provider values
     */
    public static function getAll(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * Check if provider is stateless (doesn't store user information)
     */
    public function isStateless(): bool
    {
        return in_array($this, [
            self::SESSION,
            self::CACHE,
            self::REDIS,
            self::FILE,
        ]);
    }

    /**
     * Check if provider is stateful (stores user information)
     */
    public function isStateful(): bool
    {
        return ! $this->isStateless();
    }

    /**
     * Check if provider supports merging
     */
    public function supportsMerging(): bool
    {
        return $this === self::DATABASE;
    }

    /**
     * Get provider display name
     */
    public function getDisplayName(): string
    {
        return match ($this) {
            self::SESSION => 'Session Storage',
            self::CACHE => 'Cache Storage',
            self::DATABASE => 'Database Storage',
            self::REDIS => 'Redis Storage',
            self::FILE => 'File Storage',
        };
    }

    /**
     * Get provider description
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::SESSION => 'Stores cart data in user session (temporary)',
            self::CACHE => 'Stores cart data in cache system (configurable TTL)',
            self::DATABASE => 'Stores cart data in database (persistent, supports user association)',
            self::REDIS => 'Stores cart data in Redis (fast, configurable TTL)',
            self::FILE => 'Stores cart data in file system (persistent, slower)',
        };
    }

    /**
     * Create enum instance from string
     */
    public static function fromString(string|self $provider): self
    {
        if ($provider instanceof self) {
            return $provider;
        }

        return self::from($provider);
    }

    /**
     * Check if provider string is valid
     */
    public static function isValid(string|self $provider): bool
    {
        if ($provider instanceof self) {
            return true;
        }

        return in_array($provider, self::getAll());
    }
}
