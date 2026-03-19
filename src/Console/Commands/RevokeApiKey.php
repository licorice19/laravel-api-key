<?php

namespace Licorice19\ApiKey\Console\Commands;

use Licorice19\ApiKey\Models\ApiKey;

class RevokeApiKey extends BaseApiKeyCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:revoke {id : ID API key for revoke}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Revoking API key';

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

        if (!$apiKey->is_active) {
            $this->warn("API key '{$apiKey->name}' already revoked.");
            return self::SUCCESS;
        }

        $apiKey->revoke();

        $this->info("API key '{$apiKey->name}' successfuly revoked.");

        return self::SUCCESS;
    }
}