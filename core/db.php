<?php
// Translation portal: database connection, retries and schema upgrades.

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    // Pin the connection collation to match the tables. Some servers default
    // to utf8mb4_general_ci, which clashes with the tables' utf8mb4_unicode_ci.
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+00:00', SESSION innodb_lock_wait_timeout = 10");
    return $pdo;
}

// Run $fn and retry up to twice if MySQL reports a deadlock (1213) or lock
// wait timeout (1205). With single-statement writes this is very unlikely.
function retrying(callable $fn) {
    for ($i = 0; ; $i++) {
        try { return $fn(); }
        catch (PDOException $e) {
            $code = $e->errorInfo[1] ?? 0;
            if (($code === 1213 || $code === 1205) && $i < 2) { usleep(100000 * ($i + 1)); continue; }
            throw $e;
        }
    }
}

// MySQL named locks are shared by the whole server, so include the database
// name: two portals on one server must not block each other.
function lock_name(string $what): string {
    return 'tp_' . $what . '_' . substr(md5(DB_NAME), 0, 12);
}

// Upgrades an older database in place (run automatically, once).
//   v2: deleted_at/deleted_by on translations and notes, and the target text
//       that was imported from the source file is credited to the AI label.
function ensure_schema(): void {
    $pdo = db();
    $v = (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema_version'")->fetchColumn() ?: 1);
    if ($v >= 2) return;
    $lock = lock_name('schema');
    if ((int)$pdo->query("SELECT GET_LOCK('$lock', 10)")->fetchColumn() !== 1) return;
    try {
        $v = (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema_version'")->fetchColumn() ?: 1);
        if ($v >= 2) return;
        foreach (['translations', 'notes'] as $t) {
            if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE 'deleted_at'")->fetch()) {
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by VARCHAR(80) NULL");
            }
        }
        $pdo->prepare("UPDATE translations SET translator = ? WHERE source = 'ai' AND translator = 'translations.js'")
            ->execute([portal()['ai']['label']]);
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('schema_version', '2') ON DUPLICATE KEY UPDATE v = '2'");
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('$lock')");
    }
}
