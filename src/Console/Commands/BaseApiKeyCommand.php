<?php

namespace Licorice19\ApiKey\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

abstract class BaseApiKeyCommand extends Command
{
    /**
     * Check if the package is ready to use.
     *
     * @return bool
     */
    protected function isReady(): bool
    {
        // Проверяем существование таблицы api_keys
        try {
            if (!Schema::hasTable('api_keys')) {
                $this->showSetupInstructions();
                return false;
            }
        } catch (\Exception $e) {
            $this->showSetupInstructions();
            return false;
        }

        return true;
    }

    /**
     * Show package setup instructions.
     *
     * @return void
     */
    protected function showSetupInstructions(): void
    {
        $this->error('Table "api_keys" not found.');
        $this->newLine();
        $this->line('First, run the following commands:');
        $this->newLine();
        $this->line('  <comment>php artisan vendor:publish --tag=api-key-migrations</comment>');
        $this->line('  <comment>php artisan migrate</comment>');
        $this->newLine();
        $this->line('Also publish the configuration file:');
        $this->line('  <comment>php artisan vendor:publish --tag=api-key-config</comment>');
    }

    /**
     * Execute the command.
     * This method must be implemented in child classes.
     *
     * @return int
     */
    abstract protected function executeCommand(): int;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->isReady()) {
            return self::FAILURE;
        }

        return $this->executeCommand();
    }
}