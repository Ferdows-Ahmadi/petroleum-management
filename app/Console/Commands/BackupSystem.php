<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;

class BackupSystem extends Command
{
    protected $signature = 'app:backup {destination : A protected folder outside public/}';

    protected $description = 'Back up MySQL and uploaded files without exposing credentials';

    public function handle(SystemBackupService $backups): int
    {
        $wasDown = app()->isDownForMaintenance();
        try {
            if (! $wasDown) {
                $this->call('down');
            }
            $this->info($backups->backup($this->argument('destination')));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } finally {
            if (! $wasDown) {
                $this->call('up');
            }
        }
    }
}
