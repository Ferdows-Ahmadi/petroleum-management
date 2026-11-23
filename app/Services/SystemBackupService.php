<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SystemBackupService
{
    public function backup(string $parent): string
    {
        $config = $this->connection();
        $parent = $this->outsideWebRoot($parent);
        File::ensureDirectoryExists($parent, 0700);
        $directory = $parent.DIRECTORY_SEPARATOR.'backup-'.now()->format('Ymd-His').'-'.bin2hex(random_bytes(3));
        File::makeDirectory($directory, 0700);
        try {
            // Windows mysqldump cannot reliably open Unicode --result-file paths.
            // PHP owns the output file and streams SQL without buffering the database.
            $sql = fopen($directory.'/database.sql', 'xb');
            if ($sql === false) {
                throw new \RuntimeException('Cannot create the database backup file.');
            }
            try {
                $this->process('mysqldump', [...$this->arguments($config), '--single-transaction',
                    '--skip-lock-tables', '--skip-add-locks', '--no-tablespaces', '--set-gtid-purged=OFF', '--skip-triggers',
                    '--hex-blob', '--default-character-set=utf8mb4', $config['database']], $config, output: $sql);
            } finally {
                fclose($sql);
            }
            foreach (['private', 'public'] as $disk) {
                File::copyDirectory($this->diskRoot($disk), $directory.'/files/'.$disk);
            }
            $hashes = $this->hashes($directory);
            File::put($directory.'/backup.json', json_encode([
                'format' => 1, 'created_at' => now()->toIso8601String(),
                'database' => $config['database'], 'mysql' => DB::selectOne('SELECT VERSION() AS version')->version,
                'files' => $hashes, 'excludes' => ['.env', 'APP_KEY', 'logs', 'framework caches'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $directory;
        } catch (\Throwable $error) {
            File::deleteDirectory($directory);
            throw $error;
        }
    }

    public function restore(string $directory, string $expectedDatabase): void
    {
        $config = $this->connection();
        if (! hash_equals($config['database'], $expectedDatabase)) {
            throw new \RuntimeException('The confirmed database name does not match the configured target.');
        }
        // Recovery always targets an EMPTY schema. Never wipe an existing company database.
        if (DB::select('SHOW TABLES')) {
            throw new \RuntimeException('Restore requires an empty target database. Preserve the existing database and create a separate recovery schema.');
        }
        $directory = realpath($directory);
        if (! $directory || ! is_file($directory.'/backup.json') || ! is_file($directory.'/database.sql')) {
            throw new \RuntimeException('The backup folder is incomplete.');
        }
        $manifest = json_decode(File::get($directory.'/backup.json'), true, flags: JSON_THROW_ON_ERROR);
        if (($manifest['format'] ?? null) !== 1 || empty($manifest['files'])) {
            throw new \RuntimeException('Unsupported backup format.');
        }
        if ($this->hashes($directory) !== $manifest['files']) {
            throw new \RuntimeException('Backup integrity check failed.');
        }
        foreach (['private', 'public'] as $disk) {
            if (! is_dir($directory.'/files/'.$disk)) {
                throw new \RuntimeException('Uploaded-file backup is missing.');
            }
            foreach (File::allFiles($this->diskRoot($disk), true) as $file) {
                if ($file->getFilename() !== '.gitignore') {
                    throw new \RuntimeException('Restore requires empty upload directories in a separate recovery installation.');
                }
            }
        }
        $sql = fopen($directory.'/database.sql', 'rb');
        try {
            $this->process('mysql', [...$this->arguments($config), '--default-character-set=utf8mb4', $config['database']], $config, $sql);
        } finally {
            fclose($sql);
        }
        foreach (['private', 'public'] as $disk) {
            File::copyDirectory($directory.'/files/'.$disk, $this->diskRoot($disk));
        }
    }

    private function connection(): array
    {
        if (config('database.default') !== 'mysql') {
            throw new \RuntimeException('Backup and restore require MySQL.');
        }
        $config = config('database.connections.mysql');
        if (empty($config['database']) || ! preg_match('/^[A-Za-z0-9_]+$/', $config['database'])) {
            throw new \RuntimeException('A valid configured database is required.');
        }

        return $config;
    }

    private function diskRoot(string $disk): string
    {
        return config('filesystems.disks.'.($disk === 'private' ? 'local' : 'public').'.root');
    }

    private function arguments(array $config): array
    {
        return ['--protocol=TCP', '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username']];
    }

    private function process(string $tool, array $arguments, array $config, mixed $input = null, mixed $output = null): void
    {
        $binary = (new ExecutableFinder)->find($tool);
        if (! $binary) {
            throw new \RuntimeException("{$tool} is missing from PATH. Install the MySQL client tools.");
        }
        // Password is a child-process environment value, never an argument or transcript.
        $process = new Process([$binary, ...$arguments], base_path(), ['MYSQL_PWD' => (string) $config['password']]);
        $process->setTimeout(600);
        if ($input !== null) {
            $process->setInput($input);
        }
        $process->run($output === null ? null : static function (string $type, string $buffer) use ($output, $process): void {
            if ($type === Process::OUT) {
                if (fwrite($output, $buffer) !== strlen($buffer)) {
                    throw new \RuntimeException('Cannot write the database backup file.');
                }
                $process->clearOutput();
            }
        });
        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput());
            if ((string) $config['password'] !== '') {
                $detail = str_replace((string) $config['password'], '[redacted]', $detail);
            }
            throw new \RuntimeException("{$tool} failed: ".mb_substr($detail, 0, 500));
        }
    }

    private function hashes(string $directory): array
    {
        $hashes = [];
        foreach (File::allFiles($directory, true) as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if ($relative !== 'backup.json') {
                $hashes[$relative] = hash_file('sha256', $file->getPathname());
            }
        }
        ksort($hashes);

        return $hashes;
    }

    private function outsideWebRoot(string $parent): string
    {
        $absolute = str_replace('\\', '/', $parent);
        if (! preg_match('~^(?:[A-Za-z]:/|/)~', $absolute)) {
            $absolute = str_replace('\\', '/', base_path($parent));
        }
        $parts = [];
        foreach (explode('/', $absolute) as $part) {
            if ($part === '..') {
                array_pop($parts);
            } elseif ($part !== '.') {
                $parts[] = $part;
            }
        }
        $absolute = rtrim(implode('/', $parts), '/');
        $public = str_replace('\\', '/', public_path());
        if (strtolower($absolute) === strtolower($public) || str_starts_with(strtolower($absolute).'/', strtolower($public).'/')) {
            throw new \RuntimeException('Backups must be stored outside the public web directory.');
        }

        return $absolute;
    }
}
