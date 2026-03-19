<?php

namespace Licorice19\ApiKey\Console\Commands;

use Licorice19\ApiKey\Models\ApiKey;

class DeleteApiKey extends BaseApiKeyCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:delete
                            {id : ID API key for deleting}
                            {--force : Delete without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete API key';

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

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete API key '{$apiKey->name}'?")) {
                $this->info('Operation canceled.');
                return self::SUCCESS;
            }
        }

        $apiKey->delete();

        $this->info("API key '{$apiKey->name}' successfuly deleted.");

        return self::SUCCESS;
    }
}