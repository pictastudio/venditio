<?php

namespace PictaStudio\Venditio\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    protected $signature = 'venditio:install';

    protected $description = 'Install Venditio package';

    public function handle(): int
    {
        $this->components->info('Installing Venditio package...');

        $this->components->info('Publishing venditio configuration...');
        $this->call('vendor:publish', ['--tag' => 'venditio-config']);

        if (confirm('Do you want to publish venditio routes?', false)) {
            $this->components->info('Publishing venditio routes...');
            $this->call('vendor:publish', ['--tag' => 'venditio-routes']);
        }

        if (!$this->hasTranslationsTableMigrationPublished()) {
            $this->components->info('Publishing translatable migrations required for venditio');
            $this->call('vendor:publish', ['--tag' => 'translatable-migrations']);
        }

        $this->components->info('Publishing venditio migrations...');
        $this->call('vendor:publish', ['--tag' => 'venditio-migrations']);

        $this->components->info('Publishing venditio seed data...');
        $this->call('vendor:publish', ['--tag' => 'venditio-data']);

        if (confirm('Do you want to publish bruno api files?', false)) {
            $this->components->info('Publishing bruno api files...');
            $this->call('vendor:publish', ['--tag' => 'venditio-bruno']);
        }

        if (confirm('Do you want to run migrations now?')) {
            $this->call('migrate');
        }

        $this->components->info('Venditio package installed successfully.');

        return self::SUCCESS;
    }

    /**
     * Check if the create_translations_table migration has already been published to the application.
     */
    protected function hasTranslationsTableMigrationPublished(): bool
    {
        $migrationsPath = database_path('migrations');

        if (!File::isDirectory($migrationsPath)) {
            return false;
        }

        $files = File::files($migrationsPath);

        foreach ($files as $file) {
            if (str_contains($file->getFilename(), 'create_translations_table')) {
                return true;
            }
        }

        return false;
    }
}
