<?php

namespace HCart\LaravelMultiCart\Commands;

use HCart\LaravelMultiCart\Services\CartManager;
use Illuminate\Console\Command;

class CartMigrateProviderCommand extends Command
{
    protected $signature = 'cart:migrate-provider {from : Source provider} {to : Destination provider} {--force : Force migration without confirmation}';

    protected $description = 'Migrate carts from one provider to another';

    public function handle(): int
    {
        $fromProvider = $this->argument('from');
        $toProvider = $this->argument('to');
        $force = $this->option('force');

        if ($fromProvider === $toProvider) {
            $this->error('Source and destination providers cannot be the same.');

            return self::FAILURE;
        }

        if (! $force && ! $this->confirm("Are you sure you want to migrate carts from {$fromProvider} to {$toProvider}?")) {
            $this->info('Migration cancelled.');

            return self::SUCCESS;
        }

        try {
            $cartManager = app(CartManager::class);

            $sourceProvider = $cartManager->getProvider($fromProvider);
            $destinationProvider = $cartManager->getProvider($toProvider);

            $this->info("Starting migration from {$fromProvider} to {$toProvider}...");

            // For database provider, we can get all carts
            if ($fromProvider === 'database') {
                $this->migrateDatabaseCarts($sourceProvider, $destinationProvider);
            } else {
                $this->error('Migration from non-database providers is not currently supported.');
                $this->info('This feature requires provider-specific implementation to list all cart names.');

                return self::FAILURE;
            }

            $this->info('Migration completed successfully.');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Migration failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function migrateDatabaseCarts($sourceProvider, $destinationProvider): void
    {
        $cartModel = app('LaravelMultiCart.config')->getCartModel();
        $carts = $cartModel::with('items')->get();

        $bar = $this->output->createProgressBar($carts->count());
        $bar->start();

        $migratedCount = 0;
        $failedCount = 0;

        foreach ($carts as $cart) {
            try {
                $cartData = [
                    'name' => $cart->name,
                    'config' => $cart->config,
                    'user_id' => $cart->user_id,
                    'session_id' => $cart->session_id,
                    'items' => $cart->items->toArray(),
                    'created_at' => $cart->created_at->toISOString(),
                    'updated_at' => $cart->updated_at->toISOString(),
                ];

                if ($destinationProvider->put($cart->name, $cartData)) {
                    $migratedCount++;
                } else {
                    $failedCount++;
                }
            } catch (\Exception $e) {
                $failedCount++;
                $this->newLine();
                $this->error("Failed to migrate cart {$cart->name}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Migration completed: {$migratedCount} carts migrated, {$failedCount} failed.");

        if ($migratedCount > 0 && $this->confirm('Do you want to remove the original carts from the source provider?')) {
            $sourceProvider->flush();
            $this->info('Original carts removed from source provider.');
        }
    }
}
