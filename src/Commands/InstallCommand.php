<?php

declare(strict_types=1);

namespace Larafied\Commands;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature   = 'larafied:install';
    protected $description = 'Install Larafied into your Laravel application';

    public function handle(): int
    {
        $this->info('Installing Larafied...');

        $this->ensureStorageDirectory();
        $this->publishAssets();

        $prefix = config('larafied.prefix', 'larafied');
        $url    = rtrim(config('app.url', 'http://localhost'), '/');

        $this->newLine();
        $this->info('Larafied installed successfully.');
        $this->line("  URL: <href={$url}/{$prefix}>{$url}/{$prefix}</>");
        $this->newLine();
        $this->line('  To configure, publish the config file:');
        $this->line('  <comment>php artisan vendor:publish --tag=larafied-config</comment>');

        return self::SUCCESS;
    }

    private function ensureStorageDirectory(): void
    {
        $path = storage_path('larafied');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
            $this->line('  Created: <comment>storage/larafied/</comment>');
        }

        // Prevent workspace.db from being committed accidentally
        $gitignore = $path.DIRECTORY_SEPARATOR.'.gitignore';

        if (! file_exists($gitignore)) {
            file_put_contents($gitignore, "workspace.db\nlicense.json\n");
        }
    }

    private function publishAssets(): void
    {
        $this->call('vendor:publish', [
            '--tag'   => 'larafied-assets',
            '--force' => true,
        ]);
    }
}
