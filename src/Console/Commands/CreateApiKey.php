<?php

namespace Licorice19\ApiKey\Console\Commands;

use Licorice19\ApiKey\Models\ApiKey;

class CreateApiKey extends BaseApiKeyCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api-key:create
                            {name : name of API key}
                            {--expires= : expiry date (формат: Y-m-d H:i:s)}
                            {--rate-limit= : maximum number of requests per period}
                            {--rate-period= : rate limit period in seconds (default: 60)}
                            {--tag= : tag for access control (default: default)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create new API key';

    /**
     * Execute the console command.
     */
    protected function executeCommand(): int
    {
        $name = $this->argument('name');
        $expiresAt = null;

        if ($this->option('expires')) {
            try {
                $expiresAt = new \DateTime($this->option('expires'));
            } catch (\Exception $e) {
                $this->error('Invalid date format. Use the following format: Y-m-d H:i:s');
                return self::FAILURE;
            }
        }

        $rateLimit = $this->option('rate-limit') ? (int) $this->option('rate-limit') : null;
        $rateLimitPeriod = $this->option('rate-period') ? (int) $this->option('rate-period') : null;
        $tag = $this->option('tag') ?: config('api-key.default_tag', 'default');

        $result = ApiKey::createKey($name, $expiresAt, $rateLimit, $rateLimitPeriod, $tag);

        $this->info('API key successfully created!');
        $this->newLine();
        $this->line('<comment>Important:</comment> Save this key, it will not be shown again.');
        $this->newLine();
        $this->table(
            ['Parameter', 'Value'],
            [
                ['ID', $result['model']->id],
                ['Name', $result['model']->name],
                ['Tag', $result['model']->tag],
                ['API key', $result['key']],
                ['Is active', $result['model']->is_active ? 'Yes' : 'No'],
                ['Expires at', $result['model']->expires_at?->format('Y-m-d H:i:s') ?? 'Never'],
                ['Rate limit', $result['model']->rate_limit ? "{$result['model']->rate_limit} req / {$result['model']->rate_limit_period}s" : 'Unlimited'],
            ]
        );

        return self::SUCCESS;
    }
}
