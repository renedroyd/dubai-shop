# Deployment process templates

These examples are starting points, not ready-to-install production configuration.

## Queue worker

1. Replace `/var/www/dubai-shop/current`, `/usr/bin/php`, and `www-data` with the actual release path, PHP binary, and operating-system account.
2. Change the queue connection in the `queue:work` command to match `QUEUE_CONNECTION` (for example, `redis` instead of `database`).
3. Install the file under Supervisor's configured include directory.
4. Run `supervisorctl reread`, `supervisorctl update`, and `supervisorctl status dubai-shop-worker:*`.
5. After each deployment, run `php artisan queue:restart` and verify the worker comes back online.

Do not use `php artisan serve` as the public production application server. Use a persistent, supported PHP application stack (for example PHP-FPM behind Apache or Caddy, or another supported application server) and supervise its processes. This repository does not assume Nginx is installed.

## Scheduler

Install `cron/dubai-shop-scheduler` after replacing the path and PHP binary. Verify cron's execution logs and confirm the scheduler runs as the same application user that owns the writable Laravel directories.

## Cloudflare Tunnel

Configure the tunnel origin to the actual persistent application listener. Keep the origin inaccessible from untrusted networks where possible. Determine the source IP that the Laravel process actually sees before setting `TRUSTED_PROXIES`; never copy a broad proxy range or use `*` without confirming the complete trust boundary. Re-run `php artisan config:cache` and `php artisan app:production-readiness` after changing environment values.

## Backups and restoration

Database engine, storage paths, backup destination, encryption and retention must be selected for the actual host. Do not run a generic backup script until those values are known. Back up the database and both private/public uploaded files, then restore them into an isolated environment and verify login, orders, payment proofs and product/banner media.
