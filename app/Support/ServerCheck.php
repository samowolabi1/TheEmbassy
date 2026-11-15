<?php

namespace App\Support;

use App\Services\Content\Images;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Checks the things a server, especially shared hosting, must have for the app to work:
 * PHP and its extensions, writable folders, the cron job, email, uploads and safe settings.
 * Shown on admin › Settings & roles › Server check, and by `php artisan app:check`.
 */
class ServerCheck
{
    public const HEARTBEAT = 'scheduler:heartbeat';

    /**
     * @return list<array{group: string, label: string, status: 'ok'|'warn'|'fail', detail: string, fix: ?string}>
     */
    public function run(): array
    {
        $checks = [];
        $add = function (string $group, string $label, string $status, string $detail, ?string $fix = null) use (&$checks) {
            $checks[] = compact('group', 'label', 'status', 'detail', 'fix');
        };

        // PHP
        $add('PHP', 'PHP version', version_compare(PHP_VERSION, '8.3.0', '>=') ? 'ok' : 'fail', PHP_VERSION, 'Choose PHP 8.3 or newer in your hosting control panel (cPanel › Select PHP Version or MultiPHP Manager).');
        $driver = DB::connection()->getDriverName();
        $required = ['mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'curl', 'gd', 'dom', 'filter', 'pdo_'.$driver];
        foreach ($required as $ext) {
            $add('PHP', "Extension: {$ext}", extension_loaded($ext) ? 'ok' : 'fail', extension_loaded($ext) ? 'Installed' : 'Missing', "Turn on the {$ext} extension in your hosting control panel (Select PHP Version › Extensions).");
        }
        if (extension_loaded('gd')) {
            $add('PHP', 'Pictures saved as WebP', Images::webp() ? 'ok' : 'warn', Images::webp() ? 'Supported' : 'Not supported: pictures are saved as JPEG instead (larger files)', 'Ask your host for GD with WebP support.');
        }
        foreach (['exif' => 'Phone photos are turned the right way up', 'gmp' => 'App notifications send faster', 'intl' => 'Better number and date formatting'] as $ext => $why) {
            $add('PHP', "Extension: {$ext} (optional)", extension_loaded($ext) ? 'ok' : 'warn', extension_loaded($ext) ? 'Installed' : "Missing. {$why} with it.", "Turn on {$ext} in Select PHP Version › Extensions, if offered.");
        }

        // Limits
        $memory = $this->bytes((string) ini_get('memory_limit'));
        $add('Limits', 'Memory limit', $memory === -1 || $memory >= 256 * 1024 ** 2 ? 'ok' : 'warn', (string) ini_get('memory_limit'), 'Set memory_limit to 256M or more (Select PHP Version › Options) so big photos can be resized.');
        $upload = min($this->bytes((string) ini_get('upload_max_filesize')), $this->bytes((string) ini_get('post_max_size')));
        $add('Limits', 'Largest upload', $upload === -1 || $upload >= 16 * 1024 ** 2 ? 'ok' : 'warn', ini_get('upload_max_filesize').' per file, '.ini_get('post_max_size').' per form', 'Set upload_max_filesize to 16M and post_max_size to 200M or more for photo albums and the media file library.');
        $time = (int) ini_get('max_execution_time');
        $add('Limits', 'Time allowed per request', $time === 0 || $time >= 60 ? 'ok' : 'warn', $time === 0 ? 'No limit' : "{$time} seconds", 'Set max_execution_time to 60 or more.');

        // Folders
        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache'), 'storage/app/public (uploads)' => storage_path('app/public')] as $name => $path) {
            $add('Folders', "Writable: {$name}", is_dir($path) && is_writable($path) ? 'ok' : 'fail', is_dir($path) ? (is_writable($path) ? 'Writable' : 'Not writable') : 'Missing', "Set the folder's permissions to 755 (or 775) in File Manager, and make sure it belongs to your account.");
        }
        $linked = is_link(public_path('storage')) || is_dir(public_path('storage'));
        $add('Folders', 'Uploaded pictures', 'ok', $linked ? 'Served directly (public/storage link exists)' : 'Served through the app (no public/storage link; that works, slightly slower)');

        // Scheduled jobs
        $beat = Cache::get(self::HEARTBEAT);
        $last = $beat ? Carbon::createFromTimestamp($beat) : null;
        $add('Scheduled jobs', 'Cron job', match (true) {
            $last === null => 'fail',
            $last->lt(now()->subMinutes(20)) => 'fail',
            $last->lt(now()->subMinutes(6)) => 'warn',
            default => 'ok',
        }, $last ? 'Last ran '.$last->diffForHumans() : 'Has never run', 'In cPanel › Cron Jobs, add one that runs every minute: cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');

        // Settings
        $production = app()->environment('production');
        $add('Settings', 'Live mode (APP_ENV)', $production ? 'ok' : 'warn', app()->environment(), 'Set APP_ENV=production in .env on the live site.');
        $add('Settings', 'Error details hidden (APP_DEBUG)', config('app.debug') ? ($production ? 'fail' : 'warn') : 'ok', config('app.debug') ? 'On: visitors could see error details' : 'Off', 'Set APP_DEBUG=false in .env on the live site.');
        $add('Settings', 'App key (APP_KEY)', filled(config('app.key')) ? 'ok' : 'fail', filled(config('app.key')) ? 'Set' : 'Missing', 'Run php artisan key:generate (or paste a key into .env).');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('Settings', 'Site address (APP_URL)', $https ? 'ok' : ($production ? 'fail' : 'warn'), (string) config('app.url'), 'Set APP_URL to the site\'s https:// address, and turn on free SSL (AutoSSL / Let\'s Encrypt) in your control panel.');
        $mailer = (string) config('mail.default');
        $add('Settings', 'Email sending (MAIL_MAILER)', in_array($mailer, ['log', 'array'], true) ? ($production ? 'fail' : 'warn') : 'ok', $mailer, 'Create a mailbox in your hosting (e.g. no-reply@yourchurch.org) and put its SMTP details in .env: MAIL_MAILER=smtp, MAIL_HOST, MAIL_PORT=465, MAIL_USERNAME, MAIL_PASSWORD, MAIL_SCHEME=smtps.');

        // Database
        try {
            DB::select('select 1');
            $add('Database', 'Connection', 'ok', $driver.' · '.DB::connection()->getDatabaseName());
        } catch (Throwable $e) {
            $add('Database', 'Connection', 'fail', 'Cannot connect', 'Check DB_HOST, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
        }

        return $checks;
    }

    public static function beat(): void
    {
        Cache::put(self::HEARTBEAT, now()->timestamp, now()->addDays(2));
    }

    private function bytes(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === '-1') {
            return -1;
        }
        $n = (int) $raw;

        return match (strtolower(substr($raw, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
