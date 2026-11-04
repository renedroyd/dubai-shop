<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProductionReadinessCheck extends Command
{
    protected $signature = 'app:production-readiness';

    protected $description = 'Validate critical application settings before a production deployment';

    public function handle(): int
    {
        $checks = [
            ['APP_ENV is production', config('app.env') === 'production', 'Set APP_ENV=production.'],
            ['Debug mode is disabled', config('app.debug') === false, 'Set APP_DEBUG=false.'],
            ['Application URL uses HTTPS', parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https', 'Set APP_URL to the public HTTPS URL.'],
            ['Application key is configured', filled(config('app.key')), 'Generate and persist a unique APP_KEY.'],
            ['Session cookies are secure', config('session.secure') === true, 'Set SESSION_SECURE_COOKIE=true.'],
            ['Session cookies are HTTP-only', config('session.http_only') === true, 'Set SESSION_HTTP_ONLY=true.'],
            ['Session data is encrypted', config('session.encrypt') === true, 'Set SESSION_ENCRYPT=true.'],
            ['Session SameSite policy is restrictive', in_array(config('session.same_site'), ['lax', 'strict'], true), 'Use SESSION_SAME_SITE=lax or strict.'],
            ['Session driver is persistent', ! in_array(config('session.driver'), ['array', 'cookie'], true), 'Use a persistent server-side session driver.'],
            ['Queue driver is asynchronous', ! in_array(config('queue.default'), ['sync', 'null'], true), 'Configure a persistent asynchronous queue and supervise its workers.'],
            ['Cache driver is persistent', ! in_array(config('cache.default'), ['array', 'null'], true), 'Configure a persistent cache store.'],
            ['Mail transport is configured', ! in_array(config('mail.default'), ['log', 'array'], true), 'Configure SMTP or a production mail provider.'],
            ['Mail sender address is configured', filled(config('mail.from.address')) && ! str_ends_with((string) config('mail.from.address'), '@example.com'), 'Set MAIL_FROM_ADDRESS to an address on your domain.'],
            ['Trusted proxy configuration is explicit', ! in_array('*', config('app.trusted_proxies', []), true), 'Do not trust every proxy; configure only controlled proxy IPs/CIDRs.'],
        ];

        $failed = 0;

        foreach ($checks as [$label, $passed, $remediation]) {
            if ($passed) {
                $this->components->twoColumnDetail($label, '<fg=green;options=bold>PASS</>');
                continue;
            }

            $failed++;
            $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</>');
            $this->line("  <fg=yellow>{$remediation}</>");
        }

        try {
            DB::connection()->getPdo();
            $this->components->twoColumnDetail('Database connection', '<fg=green;options=bold>PASS</>');
        } catch (\Throwable $exception) {
            $failed++;
            $this->components->twoColumnDetail('Database connection', '<fg=red;options=bold>FAIL</>');
            $this->line('  <fg=yellow>Verify database host, credentials, network access and migrations.</>');
            report($exception);
        }

        foreach ([
            'Private storage' => config('filesystems.disks.local.root'),
            'Public storage' => config('filesystems.disks.public.root'),
        ] as $label => $path) {
            $writable = is_string($path) && is_dir($path) && is_writable($path);

            if ($label === 'Private storage' && is_string($path)) {
                $publicPath = rtrim(public_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                $writable = $writable && ! str_starts_with($path, $publicPath);
            }

            if ($writable) {
                $this->components->twoColumnDetail($label, '<fg=green;options=bold>PASS</>');
                continue;
            }

            $failed++;
            $this->components->twoColumnDetail($label, '<fg=red;options=bold>FAIL</>');
            $this->line('  <fg=yellow>Ensure the configured storage directory exists and is writable; private files must remain outside the public web root.</>');
        }

        if ($failed > 0) {
            $this->newLine();
            $this->components->error("Production readiness check found {$failed} issue(s). Review the remediation above.");
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->success('Application configuration passed the production readiness checks.');
        $this->comment('This command does not verify external HTTPS, SMTP delivery, running queue workers, backups, restore procedures or monitoring.');

        return self::SUCCESS;
    }
}
