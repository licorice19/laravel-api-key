<?php

namespace Licorice19\ApiKey\Console\Commands;

use Licorice19\ApiKey\Models\ApiKey;

class ListApiKeys extends BaseApiKeyCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:list
                            {--active-only : Show only active keys}
                            {--expired : Show only expired keys}
                            {--tag= : Filter by tag}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show list of all API keys';

    /**
     * Execute the console command.
     */
    protected function executeCommand(): int
    {
        $query = ApiKey::query()->orderBy('created_at', 'desc');

        if ($this->option('active-only')) {
            $query->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                });
        }

        if ($this->option('expired')) {
            $query->where('expires_at', '<', now());
        }

        if ($this->option('tag')) {
            $query->where('tag', $this->option('tag'));
        }

        $apiKeys = $query->get();

        if ($apiKeys->isEmpty()) {
            $this->info('API keys not found.');
            return self::SUCCESS;
        }

        $rows = $apiKeys->map(function ($key) {
            $status = $key->is_active ? 'Active' : 'Inactive';
            if ($key->expires_at && now()->gt($key->expires_at)) {
                $status = 'Expired';
            }

            return [
                'id' => $key->id,
                'name' => $key->name ?? '-',
                'tag' => $key->tag,
                'status' => $status,
                'expires_at' => $key->expires_at?->format('Y-m-d H:i:s') ?? 'Never',
                'last_used_at' => $key->last_used_at?->format('Y-m-d H:i:s') ?? 'Never',
                'created_at' => $key->created_at->format('Y-m-d H:i:s'),
            ];
        });

        $this->table(
            ['ID', 'Name', 'Tag', 'Status', 'Expires at', 'Last used at', 'Created at'],
            $rows->toArray()
        );

        $this->newLine();
        $this->info("Total keys: {$apiKeys->count()}");

        return self::SUCCESS;
    }
}