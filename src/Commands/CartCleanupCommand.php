<?php

namespace HCart\LaravelMultiCart\Commands;

use Illuminate\Console\Command;

class CartCleanupCommand extends Command
{
    protected $signature = 'cart:cleanup {--provider= : The provider to clean up} {--force : Force cleanup without confirmation}';

    protected $description = 'Clean up expired carts';

    public function handle(): int
    {
        $provider = $this->option('provider');
        $force = $this->option('force');

        if (! $force && ! $this->confirm('Are you sure you want to clean up expired carts?')) {
            $this->info('Cleanup cancelled.');

            return self::SUCCESS;
        }

        $expireAfter = config('laravel-multi-cart.cleanup.expire_after', 168); // hours
        $cutoffDate = now()->subHours($expireAfter);

        $cartModel = app('LaravelMultiCart.config')->getCartModel();

        if ($provider === 'database' || ! $provider) {
            $this->cleanupDatabaseCarts($cartModel, $cutoffDate);
        }

        if ($provider && $provider !== 'database') {
            $this->cleanupProviderCarts($provider);
        }

        $this->info('Cart cleanup completed successfully.');

        return self::SUCCESS;
    }

    protected function cleanupDatabaseCarts(string $cartModel, $cutoffDate): void
    {
        $this->info('Cleaning up database carts...');

        // Clean up expired carts
        $expiredCount = $cartModel::where('expires_at', '<', now())->count();
        $cartModel::where('expires_at', '<', now())->forceDelete();

        // Clean up old carts
        $oldCount = $cartModel::where('updated_at', '<', $cutoffDate)->count();
        $cartModel::where('updated_at', '<', $cutoffDate)->forceDelete();

        $this->info("Deleted {$expiredCount} expired carts and {$oldCount} old carts from database.");
    }

    protected function cleanupProviderCarts(string $provider): void
    {
        $this->info("Cleaning up {$provider} carts...");

        try {
            $cartManager = app(\HCart\LaravelMultiCart\Services\CartManager::class);
            $cartProvider = $cartManager->getProvider($provider);

            // For non-database providers, we can only flush all carts
            // Individual cleanup would require provider-specific implementation
            if ($this->confirm("This will flush ALL carts from the {$provider} provider. Continue?")) {
                $cartProvider->flush();
                $this->info("Flushed all carts from {$provider} provider.");
            }
        } catch (\Exception $e) {
            $this->error("Failed to cleanup {$provider} carts: ".$e->getMessage());
        }
    }
}
