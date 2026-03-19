<?php

namespace Licorice19\ApiKey\Console\Commands;

use Licorice19\ApiKey\Models\ApiKey;

class ActivateApiKey extends BaseApiKeyCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:activate {id : ID API key for activation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate API key';

    /**
     * Execute the console command.
     */
    protected function executeCommand(): int
    {
        $id = $this->argument('id');

        $apiKey = ApiKey::find($id);

        if (!$apiKey) {
            $this->error("API key with ID {$id} not found.");
            return self::FAILURE;
        }

        if ($apiKey->is_active) {
            $this->warn("API key '{$apiKey->name}' already active.");
            return self::SUCCESS;
        }

        $apiKey->activate();

        $this->info("API key '{$apiKey->name}' successfuly activated.");

        return self::SUCCESS;
    }
}