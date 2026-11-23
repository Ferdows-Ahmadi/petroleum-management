<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\SystemBackupService;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MySqlBackupRestoreTest extends TestCase
{
    public function test_mysql_backup_recovers_committed_rows_and_both_upload_disks_and_rejects_tampering(): void
    {
        if (config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'petroleum_management_test') {
            $this->markTestSkipped('Requires dedicated disposable MySQL test schemas.');
        }
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
}
