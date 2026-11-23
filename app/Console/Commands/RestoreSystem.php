<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;

class RestoreSystem extends Command
{
    protected $signature = 'app:restore {backup : Verified backup folder} {--database= : Explicit empty recovery database name}';

    protected $description = 'Restore only into an empty MySQL schema and empty upload directories';

    public function handle(SystemBackupService $backups): int
    {
        if (! $this->option('database')) {
            $this->error('Specify --database with the configured EMPTY recovery schema name.');

            return self::FAILURE;
        }
        $wasDown = app()->isDownForMaintenance();
        $restored = false;
        try {
            if (! $wasDown) {
                $this->call('down');
            }
            $backups->restore($this->argument('backup'), $this->option('database'));
            $restored = true;
            $this->info('Database and uploaded files restored. Verify accounts and files before reopening work.');

            return self::SUCCESS;
        } catch (\Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        } finally {
            // A failed/partial recovery stays closed for installer inspection.
            if (! $wasDown && $restored) {
                $this->call('up');
            }
        }
    }
}
