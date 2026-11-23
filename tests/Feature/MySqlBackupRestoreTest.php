<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\SystemBackupService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class MySqlBackupRestoreTest extends TestCase
{
    #[TestWith(['missing uploads'])]
    #[TestWith(['short manifest write'])]
    public function test_incomplete_backups_fail_and_are_removed(string $failure): void
    {
        $this->requireTestSchemas();
        $directory = storage_path('framework/testing/backup-failure-'.bin2hex(random_bytes(6)));
        try {
            File::ensureDirectoryExists($directory.'/private');
            config(['filesystems.disks.local.root' => $directory.'/private', 'filesystems.disks.public.root' => $directory.'/public']);
            if ($failure === 'short manifest write') {
                File::ensureDirectoryExists($directory.'/public');
                File::partialMock()->shouldReceive('put')->once()->andReturn(1);
                $expected = 'Cannot write the complete backup manifest.';
            } else {
                $expected = 'Cannot back up the configured public upload directory.';
            }
            try {
                app(SystemBackupService::class)->backup($directory.'/backups');
                $this->fail('Incomplete backup reported success.');
            } catch (\RuntimeException $error) {
                $this->assertSame($expected, $error->getMessage());
            }
            $this->assertSame([], File::directories($directory.'/backups'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_upload_copy_failure_is_reported_during_restore(): void
    {
        $this->requireTestSchemas();
        $directory = storage_path('framework/testing/restore-failure-'.bin2hex(random_bytes(6)));
        try {
            foreach (['private', 'public'] as $disk) {
                File::ensureDirectoryExists($directory.'/archive/files/'.$disk);
                File::ensureDirectoryExists($directory.'/target/'.$disk);
            }
            $statement = 'SELECT 1;';
            File::put($directory.'/archive/database.sql', $statement);
            File::put($directory.'/archive/backup.json', json_encode([
                'format' => 1, 'files' => ['database.sql' => hash('sha256', $statement)],
            ], JSON_THROW_ON_ERROR));
            config(['filesystems.disks.local.root' => $directory.'/target/private', 'filesystems.disks.public.root' => $directory.'/target/public']);
            $this->switchDatabase('petroleum_management_restore_test');
            File::partialMock()->shouldReceive('copyDirectory')
                ->with(realpath($directory.'/archive').'/files/public', $directory.'/target/public')
                ->once()->andReturn(false);
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Recovery is incomplete: cannot restore the public upload directory.');
            app(SystemBackupService::class)->restore($directory.'/archive', 'petroleum_management_restore_test');
        } finally {
            $this->switchDatabase('petroleum_management_test');
            File::deleteDirectory($directory);
        }
    }

    public function test_mysql_backup_recovers_committed_rows_and_both_upload_disks_and_rejects_tampering(): void
    {
        $this->requireTestSchemas();
        // Cover the Windows company installation path, including spaces and Dari.
        $directory = storage_path('framework/testing/پشتیبان آزمون '.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($directory.'/source-private');
        File::ensureDirectoryExists($directory.'/source-public');
        File::ensureDirectoryExists($directory.'/recovery-private');
        File::ensureDirectoryExists($directory.'/recovery-public');
        try {
            $this->artisan('migrate:fresh')->assertSuccessful();
            config(['filesystems.disks.local.root' => $directory.'/source-private', 'filesystems.disks.public.root' => $directory.'/source-public']);
            $product = Product::factory()->create(['name' => 'Fictional recovery product']);
            File::put($directory.'/source-private/ticket-test.txt', 'Fictional private evidence');
            File::put($directory.'/source-public/attachment-test.txt', 'Fictional public evidence');
            $backup = app(SystemBackupService::class)->backup($directory.'/backups');
            $this->assertFileExists($backup.'/database.sql');
            $this->assertFileDoesNotExist($backup.'/.env');
            $manifest = json_decode(File::get($backup.'/backup.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertArrayHasKey('files/private/ticket-test.txt', $manifest['files']);
            Product::whereKey($product->id)->forceDelete();
            File::delete($directory.'/source-private/ticket-test.txt');
            $this->assertDatabaseCount('products', 0);
            $this->switchDatabase('petroleum_management_restore_test');
            if (DB::select('SHOW TABLES')) {
                throw new \RuntimeException('The dedicated recovery schema must be empty before the test.');
            }
            config(['filesystems.disks.local.root' => $directory.'/recovery-private', 'filesystems.disks.public.root' => $directory.'/recovery-public']);
            File::append($backup.'/database.sql', '--tampered');
            try {
                app(SystemBackupService::class)->restore($backup, 'petroleum_management_restore_test');
                $this->fail('Tampered backup accepted.');
            } catch (\RuntimeException $error) {
                $this->assertSame('Backup integrity check failed.', $error->getMessage());
            }
            $this->assertSame([], DB::select('SHOW TABLES'));
            $sql = File::get($backup.'/database.sql');
            File::put($backup.'/database.sql', substr($sql, 0, -strlen('--tampered')));
            app(SystemBackupService::class)->restore($backup, 'petroleum_management_restore_test');
            $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Fictional recovery product']);
            $this->assertSame('Fictional private evidence', File::get($directory.'/recovery-private/ticket-test.txt'));
            $this->assertSame('Fictional public evidence', File::get($directory.'/recovery-public/attachment-test.txt'));
            try {
                app(SystemBackupService::class)->restore($backup, 'petroleum_management_restore_test');
                $this->fail('Nonempty recovery schema accepted.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('empty target database', $error->getMessage());
            }
            $this->assertDatabaseCount('products', 1);
        } finally {
            $this->switchDatabase('petroleum_management_restore_test');
            Schema::dropAllTables();
            $this->switchDatabase('petroleum_management_test');
            $this->artisan('migrate:fresh')->assertSuccessful();
            RefreshDatabaseState::$migrated = true;
            File::deleteDirectory($directory);
        }
    }

    private function switchDatabase(string $database): void
    {
        if (! in_array($database, ['petroleum_management_test', 'petroleum_management_restore_test'], true)) {
            throw new \RuntimeException('Unsafe test database.');
        }
        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');
    }

    private function requireTestSchemas(): void
    {
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'petroleum_management_test') {
            $this->markTestSkipped('Requires dedicated disposable MySQL test schemas.');
        }
    }
}
