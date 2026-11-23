<?php

namespace Tests\Unit;

use App\Services\SystemBackupService;
use Tests\TestCase;

class SystemBackupSafetyTest extends TestCase
{
    public function test_backup_cannot_publish_company_data_under_public_directory(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'petroleum_management_test']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('outside the public');
        app(SystemBackupService::class)->backup(public_path('backups'));
    }

    public function test_recovery_requires_explicit_matching_target_before_any_database_work(): void
    {
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'petroleum_management_test']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        app(SystemBackupService::class)->restore('not-a-backup', 'another_company_database');
    }
}
