<?php

namespace App\Console\Commands;

use App\Models\IntegrationApiToken;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class RotateSocialPublicatorInstallationIdentity extends Command
{
    protected $signature = 'social-publicator:rotate-installation-identity
        {--as-new-installation : Confirm this database clone will operate as a separate installation}
        {--confirm : Explicitly authorize identity and credential rotation}';

    protected $description = 'Convert a cloned Dubai Shop database into a separately paired installation';

    public function handle(): int
    {
        if (! $this->option('as-new-installation') || ! $this->option('confirm')) {
            $this->error('This destructive operation requires both --as-new-installation and --confirm.');

            return self::FAILURE;
        }

        $newApiToken = Str::random(64);
        $newWebhookSecret = Str::random(64);
        $newInstallationId = (string) Str::uuid();
        $newKeyId = 'clone-'.Str::lower(Str::random(16));

        try {
            DB::transaction(function () use ($newApiToken, $newWebhookSecret, $newInstallationId, $newKeyId): void {
                $identity = DB::table('integration_installation_identity')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $identity) {
                    throw new RuntimeException('The installation identity row does not exist.');
                }

                if (($identity->pairing_status ?? 'paired') === 'pending') {
                    throw new RuntimeException('This installation is already awaiting a new SocialPublicator pairing.');
                }

                DB::table('integration_installation_identity')
                    ->where('id', 1)
                    ->update([
                        'installation_id' => $newInstallationId,
                        'pairing_status' => 'pending',
                        'webhook_key_id' => $newKeyId,
                        'webhook_secret' => Crypt::encryptString($newWebhookSecret),
                        'updated_at' => now(),
                    ]);

                IntegrationApiToken::query()->whereNull('revoked_at')->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);

                IntegrationApiToken::query()->create([
                    'name' => 'SocialPublicator - new installation pairing',
                    'token_hash' => hash('sha256', $newApiToken),
                    'abilities' => ['catalog:read'],
                    'expires_at' => now()->addDays(365),
                ]);

                DB::table('integration_event_outbox')
                    ->whereNull('delivered_at')
                    ->update([
                        'available_at' => null,
                        'locked_at' => null,
                        'failed_at' => now(),
                        'last_error' => 'Suppressed during installation identity rotation; historical events are not replayed to the new workspace.',
                        'updated_at' => now(),
                    ]);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('New installation identity created. Outbound events are HELD until pairing is explicitly completed.');
        $this->line('Installation ID: '.$newInstallationId);
        $this->line('New read-only API token (shown once): '.$newApiToken);
        $this->line('New webhook key ID: '.$newKeyId);
        $this->line('New webhook secret (shown once): '.$newWebhookSecret);
        $this->newLine();
        $this->warn('Configure these credentials in a NEW SocialPublicator workspace before completing pairing.');
        $this->warn('Do not restore the old webhook secret or API token on this installation.');

        return self::SUCCESS;
    }
}
