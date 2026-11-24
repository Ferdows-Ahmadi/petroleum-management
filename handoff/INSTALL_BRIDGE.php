<?php

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Helper\FileInputHelper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\TerminalInputHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

// Installer-only bridge. Credentials arrive on stdin, never command arguments or logs.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
$phase = 'validation';
try {
    // Some Windows console encodings prepend/append an UTF-8 stream preamble.
    $input = json_decode(trim(stream_get_contents(STDIN), "\xEF\xBB\xBF"), true, flags: JSON_THROW_ON_ERROR);
    if (is_file($root.'/.env') || is_file($root.'/bootstrap/cache/config.php')) {
        throw new RuntimeException('Existing installation: configuration was not changed.');
    }
    foreach (['host', 'database', 'user', 'password'] as $key) {
        if (! is_string($input[$key] ?? null) || preg_match('/[\x00-\x1f]/', $input[$key])) {
            throw new RuntimeException('Invalid connection information.');
        }
    }
    $schema = $input['database'];
    $user = $input['user'];
    if (! in_array($input['action'] ?? '', ['probe', 'install'], true) ||
        ! preg_match('~^http://127\.0\.0\.1:([0-9]{4,5})$~', $input['url'] ?? '', $urlMatch) ||
        (int) $urlMatch[1] < 1024 || (int) $urlMatch[1] > 65535) {
        throw new RuntimeException('Invalid installer action or local address.');
    }
    if (! preg_match('/^[A-Za-z0-9_]{1,64}$/', $schema) ||
        in_array(strtolower($schema), ['mysql', 'sys', 'information_schema', 'performance_schema'], true) ||
        ! preg_match('/^[A-Za-z0-9_]{1,32}$/', $user) || strtolower($user) === 'root' ||
        ! preg_match('/^[A-Za-z0-9.:-]+$/', $input['host']) ||
        ! filter_var($input['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        throw new RuntimeException('Use a separate database and a dedicated application account.');
    }
    $dsn = 'mysql:host='.$input['host'].';port='.$input['port'].';charset=utf8mb4';
    $connect = static fn (string $name, string $password): PDO => new PDO($dsn, $name, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $assertVersion = static function (PDO $db): void {
        if (! str_starts_with($db->getAttribute(PDO::ATTR_SERVER_VERSION), '8.4.')) {
            throw new RuntimeException('MySQL 8.4.x is required.');
        }
    };
    $assertEmpty = static function (PDO $db) use ($schema): void {
        $db->exec('USE '.$schema);
        if ($db->query('SHOW TABLES')->fetchAll() || $db->query('SHOW TRIGGERS')->fetchAll()) {
            throw new RuntimeException('The database is not empty. Nothing was migrated or seeded.');
        }
        $query = $db->prepare('SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema = ?');
        $query->execute([$schema]);
        if ($query->fetchColumn()) {
            throw new RuntimeException('The database is not empty. Nothing was migrated or seeded.');
        }
    };
    $phase = 'connection';
    try {
        $db = $connect($user, $input['password']);
        $assertVersion($db);
        $assertEmpty($db);
    } catch (PDOException) {
        if (empty($input['admin'])) {
            echo json_encode(['status' => 'needs_admin']);
            exit(20);
        }
        // Administrative setup is limited to local MySQL and this empty schema/account.
        if (! in_array($input['host'], ['localhost', '127.0.0.1', '::1'], true)) {
            throw new RuntimeException('Create the empty database/account on the remote server first.');
        }
        $phase = 'administrative_setup';
        $admin = $connect((string) $input['admin']['user'], (string) $input['admin']['password']);
        $assertVersion($admin);
        $query = $admin->prepare('SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name = ?');
        $query->execute([$schema]);
        if ($query->fetchColumn()) {
            $assertEmpty($admin); // Check BEFORE any grant or other mutation.
        } else {
            $admin->exec('CREATE DATABASE '.$schema.' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        }
        $account = $admin->quote($user)."@'localhost'";
        $admin->exec('CREATE USER IF NOT EXISTS '.$account.' IDENTIFIED BY '.$admin->quote($input['password']));
        $db = $connect($user, $input['password']); // Never reset an existing user's password.
        $admin->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES, CREATE TEMPORARY TABLES ON '.$schema.'.* TO '.$account);
        $db = $connect($user, $input['password']);
        $assertEmpty($db);
        unset($admin, $input['admin']);
    }
    if (($input['action'] ?? '') === 'probe') {
        echo json_encode(['status' => 'ready']);
        exit;
    }
    $phase = 'manager_validation';
    $manager = $input['manager'] ?? [];
    $password = $manager['password'] ?? '';
    if (! is_string($manager['name'] ?? null) || trim($manager['name']) === '' || mb_strlen($manager['name']) > 150 ||
        ! filter_var($manager['email'] ?? '', FILTER_VALIDATE_EMAIL) || strlen($manager['email']) > 255 ||
        ! is_string($password) || strlen($password) < 12 || trim($password) !== $password || preg_match('/[\r\n\x00]/', $password) ||
        ! preg_match('/[a-z]/', $password) || ! preg_match('/[A-Z]/', $password) || ! preg_match('/[0-9]/', $password)) {
        throw new RuntimeException('Manager name/email or password is invalid (12+ characters, upper/lower case and a number).');
    }
    $phase = 'configuration';
    $quote = static fn (string $value): string => '"'.strtr($value, ['\\' => '\\\\', '"' => '\\"', '$' => '\\$']).'"';
    $env = file_get_contents($root.'/.env.example');
    foreach (['DB_HOST' => $input['host'], 'DB_PORT' => (string) $input['port'], 'DB_DATABASE' => $schema,
        'DB_USERNAME' => $user, 'DB_PASSWORD' => $input['password'], 'APP_URL' => $input['url']] as $key => $value) {
        $env = preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', static fn (): string => $key.'='.$quote($value), $env);
    }
    $handle = fopen($root.'/.env', 'x');
    if (! $handle || fwrite($handle, $env) !== strlen($env)) {
        throw new RuntimeException('Cannot create configuration.');
    }
    fclose($handle);
    $run = static function (array $arguments, ?string $stdin = null) use ($root, $input): void {
        $process = new Process([PHP_BINARY, 'artisan', ...$arguments], $root, [
            'PAO_DISABLE' => '1', 'DB_URL' => false, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $input['host'], 'DB_PORT' => (string) $input['port'],
            'DB_DATABASE' => $input['database'], 'DB_USERNAME' => $input['user'], 'DB_PASSWORD' => $input['password'],
        ]);
        $process->setTimeout(600);
        $process->setInput($stdin);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('Installation step failed. Preserve this installation for technical diagnosis; do not reset its database.');
        }
    };
    $phase = 'application_key';
    $run(['key:generate', '--force', '--no-interaction']);
    $phase = 'migrations';
    $run(['migrate', '--force', '--no-interaction']);
    $phase = 'default_products';
    $run(['app:initialize-products', '--no-interaction']);
    $phase = 'first_manager';
    // Use the existing validated command with a private in-memory input stream.
    // This avoids Windows console code-page/hiddeninput handling in a detached installer.
    $application = require $root.'/bootstrap/app.php';
    $kernel = $application->make(Kernel::class);
    $kernel->bootstrap();
    QuestionHelper::disableStty();
    // Symfony temporarily selects the Windows OEM code page while reading a question.
    // Load stream helpers beforehand so Unicode installation paths remain usable.
    class_exists(FileInputHelper::class);
    class_exists(TerminalInputHelper::class);
    $consoleInput = new ArrayInput(['--name' => $manager['name']]);
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $manager['email']."\n".$password."\n".$password."\n");
    rewind($stream);
    $consoleInput->setStream($stream);
    try {
        if ($kernel->all()['app:create-manager']->run($consoleInput, new BufferedOutput) !== 0) {
            throw new RuntimeException('First manager creation failed. Preserve this installation for technical diagnosis.');
        }
    } finally {
        fclose($stream);
    }
    $phase = 'installation_check';
    $run(['app:check-system', '--no-interaction']);
    echo json_encode(['status' => 'installed']);
} catch (Throwable $error) {
    // Never emit PDO/process exception details, connection strings, or input values.
    $message = $error instanceof PDOException ? 'Connection or privileges failed. Check the supplied credentials.' : $error->getMessage();
    echo json_encode(['status' => 'failed', 'phase' => $phase, 'message' => $message]);
    exit(1);
}
