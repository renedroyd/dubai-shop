<?php

namespace App\Console\Commands;

use App\Models\IntegrationApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSocialPublicatorApiToken extends Command
{
    protected $signature = 'social-publicator:token
        {--name=SocialPublicator : Human-readable token name}
        {--expires=365 : Expiration in days; use 0 for no expiration}';

    protected $description = 'Create a read-only API token for the SocialPublicator integration';

    public function handle(): int
    {
        $expiresInDays = (int) $this->option('expires');

        if ($expiresInDays < 0) {
            $this->error('The expiration must be zero or a positive number of days.');

            return self::FAILURE;
        }

        $plainTextToken = Str::random(64);

        IntegrationApiToken::query()->create([
            'name' => (string) $this->option('name'),
            'token_hash' => hash('sha256', $plainTextToken),
            'abilities' => ['catalog:read'],
            'expires_at' => $expiresInDays > 0 ? now()->addDays($expiresInDays) : null,
        ]);

        $this->newLine();
        $this->info('SocialPublicator API token created. Copy it now; it will not be shown again.');
        $this->line($plainTextToken);
        $this->newLine();

        return self::SUCCESS;
    }
}
