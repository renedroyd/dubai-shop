<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CompleteSocialPublicatorInstallationPairing extends Command
{
    protected $signature = 'social-publicator:complete-installation-pairing
        {installationId : Exact installation UUID returned by the rotation command}
        {--confirm : Confirm the new workspace has been configured and verified}';

    protected $description = 'Enable outbound events after a new SocialPublicator workspace has been paired';

    public function handle(): int
    {
        $installationId = (string) $this->argument('installationId');

        if (! $this->option('confirm') || ! Str::isUuid($installationId)) {
            $this->error('A valid installation UUID and --confirm are required.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($installationId): void {
                $identity = DB::table('integration_installation_identity')
                    ->where('id', 1)
                    ->lockForUpdate()
                    ->first();

                if (! $identity || ! hash_equals((string) $identity->installation_id, $installationId)) {
                    throw new RuntimeException('The supplied UUID does not match this installation.');
                }

                if ($identity->pairing_status !== 'pending') {
                    throw new RuntimeException('This installation is not awaiting a new pairing.');
                }

                if (! filled($identity->webhook_key_id) || ! filled($identity->webhook_secret)) {
                    throw new RuntimeException('New webhook credentials are missing; rotate the installation identity first.');
                }

                DB::table('integration_installation_identity')
                    ->where('id', 1)
                    ->update([
                        'pairing_status' => 'paired',
                        'updated_at' => now(),
                    ]);
            });
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Pairing marked complete. New outbound events may now be delivered.');

        return self::SUCCESS;
    }
}
