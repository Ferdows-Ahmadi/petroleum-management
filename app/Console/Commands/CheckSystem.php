<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckSystem extends Command
{
    protected $signature = 'app:check-system {--offline : Check files and PHP without connecting to MySQL}';

    protected $description = 'Check packaged runtime requirements without changing business data';

    public function handle(): int
    {
        $errors = [];
        if (PHP_VERSION_ID < 80500 || PHP_VERSION_ID >= 80600) {
            $errors[] = 'PHP 8.5.x is required for this verified package.';
        }
        foreach (['bcmath', 'ctype', 'date', 'dom', 'fileinfo', 'filter', 'hash', 'iconv', 'json', 'libxml',
            'mbstring', 'openssl', 'pcre', 'PDO', 'pdo_mysql', 'Phar', 'session', 'SimpleXML',
            'tokenizer', 'xml', 'xmlwriter'] as $extension) {
            if (! extension_loaded($extension)) {
                $errors[] = "Missing PHP extension: {$extension}";
            }
        }
        foreach (['storage/app/private', 'storage/app/public', 'storage/framework/cache/data',
            'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $directory) {
            $probe = base_path($directory.'/.requirements-'.bin2hex(random_bytes(6)));
            if (! is_dir(dirname($probe)) || @file_put_contents($probe, 'check') === false) {
                $errors[] = "Directory is missing or not writable: {$directory}";
            } else {
                unlink($probe);
            }
        }
        if (! is_file(public_path('build/manifest.json'))) {
            $errors[] = 'Compiled frontend manifest is missing.';
        }
        if (! $this->option('offline')) {
            if (! config('app.key')) {
                $errors[] = 'APP_KEY is missing. Run php artisan key:generate.';
            }
            if (config('database.default') !== 'mysql') {
                $errors[] = 'MySQL must be configured.';
            } else {
                try {
                    $version = DB::selectOne('SELECT VERSION() AS version')->version;
                    if (! str_starts_with($version, '8.4.')) {
                        $errors[] = 'This package requires MySQL 8.4.x.';
                    }
                    if (str_starts_with(DB::selectOne('SELECT CURRENT_USER() AS account')->account, 'root@')) {
                        $errors[] = 'Use a dedicated application MySQL account, not root.';
                    }
                } catch (\Throwable) {
                    $errors[] = 'MySQL connection failed. Check the service and local .env credentials.';
                }
            }
        }
        foreach ($errors as $error) {
            $this->error($error);
        }
        if ($errors) {
            return self::FAILURE;
        }
        $this->info('PHP, package files, writable directories'.($this->option('offline') ? '' : ', APP_KEY and MySQL').' passed.');

        return self::SUCCESS;
    }
}
