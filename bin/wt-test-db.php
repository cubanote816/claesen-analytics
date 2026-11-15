<?php

use Illuminate\Contracts\Console\Kernel;

/**
 * CLA-690 — creates or drops one per-worktree test database for bin/wt-test.
 *
 * Runs inside the Sail container from the worktree folder. It connects with the
 * app's own default connection settings, so no credential passes through the
 * shell, and it only accepts testing_<name>: the only databases the `sail` MySQL
 * user may create (docker/mysql/create-testing-database.sh), and never the dev
 * database or the shared claesen_analytics_web_testing.
 *
 * Usage: php bin/wt-test-db.php create|drop testing_<name>
 */
$action = $argv[1] ?? '';
$database = $argv[2] ?? '';

if (! in_array($action, ['create', 'drop'], true) || preg_match('/^testing_[a-z0-9_]{1,56}\z/', $database) !== 1) {
    fwrite(STDERR, "Usage: php bin/wt-test-db.php create|drop testing_<name> (lowercase letters, digits and _, at most 64 characters)\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$config = config('database.connections.'.config('database.default'));
if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, 'wt-test-db: the default connection uses '.($config['driver'] ?? 'nothing').", not MySQL; is .env linked into this worktree?\n");
    exit(1);
}

$sql = $action === 'create'
    ? "CREATE DATABASE IF NOT EXISTS `{$database}`"
    : "DROP DATABASE IF EXISTS `{$database}`";
if ($action === 'create' && preg_match('/^[a-z0-9_]+$/', $config['charset'] ?? '') && preg_match('/^[a-z0-9_]+$/', $config['collation'] ?? '')) {
    $sql .= " CHARACTER SET {$config['charset']} COLLATE {$config['collation']}";
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s', $config['host'], $config['port']),
        $config['username'],
        $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec($sql);
} catch (PDOException $e) {
    fwrite(STDERR, "wt-test-db: {$action} {$database} failed: {$e->getMessage()}\n");
    exit(1);
}

fwrite(STDERR, ($action === 'create' ? 'wt-test: using test database ' : 'wt-test: dropped test database ')."{$database}\n");
