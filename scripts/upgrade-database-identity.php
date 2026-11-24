<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

if (PHP_SAPI !== 'cli' || $argc !== 5) {
    exit(1);
}
try {
    [$script, $directory, $host, $port, $database] = $argv;
    require $directory.'/vendor/autoload.php';
    $app = require $directory.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $connection = DB::connection();
    if ($connection->getDriverName() !== 'mysql' || $connection->getConfig('host') !== $host
        || (int) $connection->getConfig('port') !== (int) $port || $connection->getDatabaseName() !== $database) {
        throw new RuntimeException;
    }
    $identity = $connection->selectOne('select database() as db, @@port as port, @@server_uuid as uuid, current_user() as account');
    if ($identity->db !== $database || (int) $identity->port !== (int) $port
        || str_starts_with(strtolower($identity->account), 'root@') || ! preg_match('/^[a-f0-9-]{36}$/iD', $identity->uuid)) {
        throw new RuntimeException;
    }
    $migrator = app('migrator');
    $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
    if (array_diff($pending, ['2026_11_24_000001_create_deal_notes_table'])) {
        throw new RuntimeException;
    }
    echo $identity->uuid.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Database identity verification failed.'.PHP_EOL);
    exit(1);
}
