<?php

namespace HCart\LaravelMultiCart\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PublishMigrationsCommand extends Command
{
    protected $signature = 'cart:publish-migrations {--force : Overwrite existing migrations}';

    protected $description = 'Publish Laravel Multi-Cart database migrations';

    public function handle(): int
    {
        $force = $this->option('force');

        $migrations = [
            'create_carts_table.php',
            'create_cart_items_table.php',
        ];

        $publishedCount = 0;
        $skippedCount = 0;

        foreach ($migrations as $migration) {
            $sourcePath = __DIR__.'/../../database/migrations/'.$migration;
            $destinationPath = database_path('migrations/'.date('Y_m_d_His').'_'.$migration);

            // Check if migration already exists
            $existingMigrations = glob(database_path('migrations/*_'.$migration));

            if (! empty($existingMigrations) && ! $force) {
                $this->warn("Migration {$migration} already exists. Use --force to overwrite.");
                $skippedCount++;

                continue;
            }

            if (! File::exists($sourcePath)) {
                $this->error("Source migration {$migration} not found.");

                continue;
            }

            // Remove existing migrations if force is used
            if ($force && ! empty($existingMigrations)) {
                foreach ($existingMigrations as $existingMigration) {
                    File::delete($existingMigration);
                    $this->info('Removed existing migration: '.basename($existingMigration));
                }
            }

            if (File::copy($sourcePath, $destinationPath)) {
                $this->info('Published migration: '.basename($destinationPath));
                $publishedCount++;
            } else {
                $this->error("Failed to publish migration: {$migration}");
            }

            // Add a small delay to ensure unique timestamps
            sleep(1);
        }

        if ($publishedCount > 0) {
            $this->info("Successfully published {$publishedCount} migration(s).");
            $this->info('Run "php artisan migrate" to create the cart tables.');
        }

        if ($skippedCount > 0) {
            $this->warn("Skipped {$skippedCount} migration(s) that already exist.");
        }

        return self::SUCCESS;
    }
}
