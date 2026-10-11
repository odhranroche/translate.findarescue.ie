<?php
// ==========================================================================
// FindARescue.ie translation tool: JSON API
//   GET  api.php?action=list[&resync=1]   all strings, translations, votes, notes
//   GET  api.php?action=export             ga: { … } block built from the votes
//   POST api.php?action=add_translation    {string_id, text, name, note?}
//   POST api.php?action=add_note           {string_id, text, name}
//   POST api.php?action=vote               {translation_id, name, vote: 1|-1|0}
//   POST api.php?action=delete             {type: translation|note, id, name, restore?}
// Every write is one short autocommit statement, and the sync runs in a
// single transaction behind a named lock, so requests don't deadlock.
// ==========================================================================

declare(strict_types=1);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void { out(['ok' => false, 'error' => $msg], $code); }

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

// ---- input cleaning --------------------------------------------------------
function clean_text($v, int $max, bool $multiline = true): string {
    if (!is_string($v)) return '';
    if (!preg_match('//u', $v)) return '';            // not valid UTF-8
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    // Drop control characters (keep newlines and tabs in multi-line fields).
    $v = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u', '', $v);
    $v = trim($v);
    return preg_match_all('/./su', $v) > $max ? '' : $v;   // character count without mbstring
}
function clean_name($v): string {
    $v = clean_text($v, 80, false);
    return preg_replace('/\s+/u', ' ', $v);
}

// ---- basic abuse protection -------------------------------------------------
function check_write_request(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    // Browsers can't send this header cross-site without a CORS preflight,
    // which we never approve, so it stops forged form posts from other sites.
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'far-translate') fail('Bad request', 403);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        $o = parse_url($origin);
        $oHost = strtolower(($o['host'] ?? '') . (isset($o['port']) ? ':' . $o['port'] : ''));
        if ($oHost !== strtolower($_SERVER['HTTP_HOST'] ?? '')) fail('Bad origin', 403);
    }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 20000) fail('Request too large', 413);

    $ip = inet_pton($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') ?: "\0\0\0\0";
    $now = time();
    db()->prepare(
        'INSERT INTO rate_limits (ip, window_start, hits) VALUES (?, ?, 1)
         ON DUPLICATE KEY UPDATE
           hits = IF(window_start < ? - 60, 1, hits + 1),
           window_start = IF(window_start < ? - 60, ?, window_start)'
    )->execute([$ip, $now, $now, $now, $now]);
    $st = db()->prepare('SELECT hits FROM rate_limits WHERE ip = ?');
    $st->execute([$ip]);
    if ((int)$st->fetchColumn() > RATE_LIMIT_PER_MIN) fail('Too many changes in a short time. Please wait a minute.', 429);
}

function body(): array {
    $b = json_decode(file_get_contents('php://input') ?: '', true);
    return is_array($b) ? $b : [];
}

// ---- reading and parsing translations.js -------------------------------------
function fetch_source(): ?string {
    $src = TRANSLATIONS_SOURCE;
    if (!preg_match('#^https?://#i', $src)) return is_readable($src) ? file_get_contents($src) : null;
    $url = $src . (strpos($src, '?') === false ? '?' : '&') . 'nocache=' . time();
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'FindARescue-translate/1.0']);
        $body = curl_exec($ch);
        $ok = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
        curl_close($ch);
        return ($ok && is_string($body)) ? $body : null;
    }
    $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 10]]));
    return $body === false ? null : $body;
}

// Decode one JS string literal ("…" or '…') to PHP text.
function js_string(string $lit): ?string {
    $q = $lit[0];
    $inner = substr($lit, 1, -1);
    // Turn JS-only escapes into JSON-compatible ones, then let json_decode do the work.
    $inner = preg_replace_callback('/\\\\(.)/su', function ($m) {
        $c = $m[1];
        if ($c === "'") return "'";
        if ($c === '"') return '\\"';
        if (strpos('\\/bfnrtu', $c) !== false) return '\\' . $c;
        if ($c === 'x') return '\\u00';   // \xHH → \u00HH
        return $c;
    }, $inner);
    if ($q === "'") $inner = preg_replace('/(?<!\\\\)"/', '\\"', $inner);
    $v = json_decode('"' . $inner . '"');
    return is_string($v) ? $v : null;
}

// Returns [key => [en, ga, todo, section, order]]. Supports one-line
// `key: "text",` entries inside the `en: {` and `ga: {` blocks.
function parse_translations(string $js): array {
    $lines = preg_split('/\R/', $js);
    $block = null; $section = null; $todoBlock = false; $order = 0;
    $en = []; $ga = [];
    $re = '/^\s*([A-Za-z_$][\w$]*)\s*:\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')\s*,?\s*(\/\/.*)?$/';
    foreach ($lines as $line) {
        if (preg_match('/^\s*(en|ga)\s*:\s*\{\s*$/', $line, $m)) { $block = $m[1]; $section = null; $todoBlock = false; continue; }
        if ($block === null) continue;
        if (preg_match('/^\s*\}\s*,?\s*$/', $line)) { $block = null; continue; }
        if (trim($line) === '') { $todoBlock = false; continue; }
        if (preg_match('#^\s*//\s*(.*)$#', $line, $m)) {
            if ($block === 'en') $section = trim($m[1]);
            $todoBlock = stripos($m[1], 'TODO (ga)') !== false;
            continue;
        }
        if (!preg_match($re, $line, $m)) continue;
        $val = js_string($m[2]);
        if ($val === null) continue;
        if ($block === 'en') { $en[$m[1]] = ['text' => $val, 'section' => $section, 'order' => ++$order]; }
        else {
            $ga[$m[1]] = ['text' => $val, 'todo_inline' => isset($m[3]) && stripos($m[3], 'TODO (ga)') !== false, 'todo_block' => $todoBlock];
            // A "// TODO (ga)" comment covers the run of English placeholders
            // right after it; the first real Irish value ends that run.
            if (!isset($en[$m[1]]) || $en[$m[1]]['text'] !== $val) $todoBlock = false;
        }
    }
    $out = [];
    foreach ($en as $k => $e) {
        $g = $ga[$k] ?? null;
        $todo = $g === null || $g['todo_inline'] || ($g['todo_block'] && $g['text'] === $e['text']);
        $out[$k] = ['en' => $e['text'], 'ga' => $g['text'] ?? null, 'todo' => $todo, 'section' => $e['section'], 'order' => $e['order']];
    }
    return $out;
}

// Bring the database in line with translations.js. Returns a status string.
function sync(bool $force): string {
    $pdo = db();
    $last = (int)($pdo->query("SELECT v FROM meta WHERE k = 'last_sync'")->fetchColumn() ?: 0);
    if (!$force && time() - $last < SYNC_INTERVAL) return 'skipped';

    // Only one sync at a time; if another request is syncing, don't wait.
    if ((int)$pdo->query("SELECT GET_LOCK('far_translate_sync', 0)")->fetchColumn() !== 1) return 'busy';
    try {
        $js = fetch_source();
        if ($js === null) return 'fetch_failed';
        $rows = parse_translations($js);
        if (count($rows) === 0) return 'parse_failed';   // never retire everything on a bad read

        retrying(function () use ($pdo, $rows) {
            $pdo->beginTransaction();
            try {
                $pdo->exec("INSERT INTO meta (k, v) VALUES ('sync_id', '1') ON DUPLICATE KEY UPDATE v = v + 1");
                $sid = (int)$pdo->query("SELECT v FROM meta WHERE k = 'sync_id'")->fetchColumn();
                // Column order matters: prev_en_text/en_changed_at read the old en_text before it is replaced.
                $up = $pdo->prepare(
                    'INSERT INTO strings (str_key, section, sort_order, en_text, file_ga, file_ga_todo, active, seen_sync)
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?)
                     ON DUPLICATE KEY UPDATE
                       prev_en_text  = IF(en_text <> VALUES(en_text), en_text, prev_en_text),
                       en_changed_at = IF(en_text <> VALUES(en_text), UTC_TIMESTAMP(), en_changed_at),
                       en_text       = VALUES(en_text),
                       section       = VALUES(section),
                       sort_order    = VALUES(sort_order),
                       file_ga       = VALUES(file_ga),
                       file_ga_todo  = VALUES(file_ga_todo),
                       active        = 1,
                       seen_sync     = VALUES(seen_sync)'
                );
                ksort($rows, SORT_STRING);   // consistent lock order
                foreach ($rows as $k => $r) {
                    $up->execute([$k, $r['section'], $r['order'], $r['en'], $r['ga'], $r['todo'] ? 1 : 0, $sid]);
                }
                $st = $pdo->prepare('UPDATE strings SET active = 0 WHERE seen_sync <> ? AND active = 1');
                $st->execute([$sid]);

                // AI column: the file's Irish where it's real, otherwise the seeded AI text.
                $pdo->exec(
                    "INSERT INTO translations (string_id, source, text, translator, en_snapshot)
                     SELECT s.id, 'ai', s.file_ga, 'Claude (AI)', s.en_text FROM strings s
                     WHERE s.file_ga_todo = 0 AND s.file_ga IS NOT NULL AND s.file_ga <> ''
                       AND NOT EXISTS (SELECT 1 FROM translations t WHERE t.string_id = s.id AND t.source = 'ai')"
                );
                $pdo->exec(
                    "INSERT INTO translations (string_id, source, text, translator, en_snapshot)
                     SELECT s.id, 'ai', a.text, 'Claude (AI)', s.en_text FROM strings s JOIN ai_seed a ON a.str_key = s.str_key
                     WHERE s.file_ga_todo = 1
                       AND NOT EXISTS (SELECT 1 FROM translations t WHERE t.string_id = s.id AND t.source = 'ai')"
                );
                $pdo->exec("INSERT INTO meta (k, v) VALUES ('last_sync', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE v = UNIX_TIMESTAMP()");
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        });
        return 'synced';
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('far_translate_sync')");
    }
}

// ---- actions -----------------------------------------------------------------
const ISO = "'%Y-%m-%dT%H:%i:%sZ'";

function action_list(): void {
    $status = sync(isset($_GET['resync']));
    $pdo = db();
    $strings = $pdo->query(
        "SELECT id, str_key, section, sort_order, en_text, prev_en_text, DATE_FORMAT(en_changed_at, " . ISO . ") AS en_changed_at,
                file_ga, file_ga_todo, active FROM strings ORDER BY active DESC, sort_order, str_key"
    )->fetchAll();
    $trs = $pdo->query(
        "SELECT id, string_id, source, text, translator, note, en_snapshot, DATE_FORMAT(created_at, " . ISO . ") AS created_at
         FROM translations WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC"
    )->fetchAll();
    $votes = $pdo->query('SELECT translation_id, voter, vote FROM votes')->fetchAll();
    $notes = $pdo->query(
        "SELECT id, string_id, author, text, DATE_FORMAT(created_at, " . ISO . ") AS created_at FROM notes WHERE deleted_at IS NULL ORDER BY created_at DESC, id DESC"
    )->fetchAll();
    $lastSync = (int)($pdo->query("SELECT v FROM meta WHERE k = 'last_sync'")->fetchColumn() ?: 0);
    out(['ok' => true, 'sync' => $status, 'last_sync' => $lastSync ? gmdate('Y-m-d\TH:i:s\Z', $lastSync) : null,
         'strings' => $strings, 'translations' => $trs, 'votes' => $votes, 'notes' => $notes]);
}

function string_exists(int $id): bool {
    $st = db()->prepare('SELECT 1 FROM strings WHERE id = ?');
    $st->execute([$id]);
    return (bool)$st->fetchColumn();
}

function action_add_translation(): void {
    check_write_request();
    $b = body();
    $sid = (int)($b['string_id'] ?? 0);
    $text = clean_text($b['text'] ?? '', 2000);
    $name = clean_name($b['name'] ?? '');
    $note = clean_text($b['note'] ?? '', 1000);
    if ($text === '') fail('Translation text is required (up to 2000 characters).');
    if ($name === '') fail('Your name is required (up to 80 characters).');
    if (!string_exists($sid)) fail('Unknown string.', 404);
    retrying(function () use ($sid, $text, $name, $note) {
        db()->prepare(
            "INSERT INTO translations (string_id, source, text, translator, note, en_snapshot)
             SELECT id, 'human', ?, ?, ?, en_text FROM strings WHERE id = ?"
        )->execute([$text, $name, $note === '' ? null : $note, $sid]);
    });
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
}

function action_add_note(): void {
    check_write_request();
    $b = body();
    $sid = (int)($b['string_id'] ?? 0);
    $text = clean_text($b['text'] ?? '', 1000);
    $name = clean_name($b['name'] ?? '');
    if ($text === '') fail('Note text is required (up to 1000 characters).');
    if ($name === '') fail('Your name is required (up to 80 characters).');
    if (!string_exists($sid)) fail('Unknown string.', 404);
    retrying(function () use ($sid, $text, $name) {
        db()->prepare('INSERT INTO notes (string_id, author, text) VALUES (?, ?, ?)')->execute([$sid, $name, $text]);
    });
    out(['ok' => true, 'id' => (int)db()->lastInsertId()]);
}

function action_vote(): void {
    check_write_request();
    $b = body();
    $tid = (int)($b['translation_id'] ?? 0);
    $name = clean_name($b['name'] ?? '');
    $vote = (int)($b['vote'] ?? 99);
    if ($name === '') fail('Your name is required (up to 80 characters).');
    if (!in_array($vote, [-1, 0, 1], true)) fail('Invalid vote.');
    $st = db()->prepare('SELECT 1 FROM translations WHERE id = ? AND deleted_at IS NULL');
    $st->execute([$tid]);
    if (!$st->fetchColumn()) fail('This translation no longer exists.', 404);
    retrying(function () use ($tid, $name, $vote) {
        if ($vote === 0) {
            db()->prepare('DELETE FROM votes WHERE translation_id = ? AND voter = ?')->execute([$tid, $name]);
        } else {
            db()->prepare(
                'INSERT INTO votes (translation_id, voter, vote) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE vote = VALUES(vote)'
            )->execute([$tid, $name, $vote]);
        }
    });
    out(['ok' => true]);
}

// Build a `ga: { … }` block. For each key it uses the accepted translation: the one with the
// highest net score above zero (latest wins ties); otherwise it keeps the
// file's current Irish, or the English placeholder marked TODO.
function action_export(): void {
    $pdo = db();
    $strings = $pdo->query('SELECT id, str_key, section, en_text, file_ga, file_ga_todo FROM strings WHERE active = 1 ORDER BY sort_order')->fetchAll();
    $best = [];
    $rows = $pdo->query(
        'SELECT t.string_id, t.text, COALESCE(SUM(v.vote), 0) AS score
         FROM translations t LEFT JOIN votes v ON v.translation_id = t.id
         WHERE t.deleted_at IS NULL
         GROUP BY t.id HAVING score > 0 ORDER BY score DESC, t.created_at DESC, t.id DESC'
    )->fetchAll();
    foreach ($rows as $r) if (!isset($best[$r['string_id']])) $best[$r['string_id']] = $r['text'];

    $enc = function ($s) { return json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); };
    $o = "  ga: {\n"; $sec = null; $first = true;
    foreach ($strings as $s) {
        if ($s['section'] !== $sec) { $sec = $s['section']; if (!$first) $o .= "\n"; if ($sec) $o .= "    // $sec\n"; }
        $first = false;
        if (isset($best[$s['id']]))                         $o .= "    {$s['str_key']}: " . $enc($best[$s['id']]) . ",\n";
        elseif (!$s['file_ga_todo'] && $s['file_ga'] !== null) $o .= "    {$s['str_key']}: " . $enc($s['file_ga']) . ",\n";
        else                                                $o .= "    {$s['str_key']}: " . $enc($s['en_text']) . ", // TODO (ga)\n";
    }
    $o .= "  },\n";
    header('Content-Type: text/plain; charset=utf-8');
    echo $o;
    exit;
}

// Setup check: open api.php?action=health in a browser. It reports the PHP
// and database setup and tries each kind of write inside a transaction that
// is rolled back, so it changes nothing.
function action_health(): void {
    $r = ['php' => PHP_VERSION, 'extensions' => [], 'db' => [], 'tables' => [], 'write_tests' => []];
    foreach (['pdo_mysql', 'curl', 'json', 'pcre'] as $ext) $r['extensions'][$ext] = extension_loaded($ext);
    $r['allow_url_fopen'] = (bool)ini_get('allow_url_fopen');
    try { $pdo = db(); } catch (Throwable $e) { $r['db']['connect'] = 'FAILED: ' . $e->getMessage(); out($r); }
    $r['db']['connect'] = 'ok';
    $r['db']['version'] = $pdo->query('SELECT VERSION()')->fetchColumn();
    $r['db']['sql_mode'] = $pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();
    $r['db']['database'] = $pdo->query('SELECT DATABASE()')->fetchColumn();
    foreach (['strings', 'translations', 'votes', 'notes', 'ai_seed', 'meta', 'rate_limits'] as $t) {
        try { $r['tables'][$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn() . ' rows'; }
        catch (Throwable $e) { $r['tables'][$t] = 'MISSING/ERROR: ' . $e->getMessage(); }
    }
    $tests = [
        'rate_limits' => function ($pdo) {
            $now = time();
            $pdo->prepare('INSERT INTO rate_limits (ip, window_start, hits) VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE hits = IF(window_start < ? - 60, 1, hits + 1), window_start = IF(window_start < ? - 60, ?, window_start)')
                ->execute(["\xff\xff\xff\xff", $now, $now, $now, $now]);
        },
        'add_translation' => function ($pdo) {
            $pdo->prepare("INSERT INTO translations (string_id, source, text, translator, note, en_snapshot)
                SELECT id, 'human', ?, ?, ?, en_text FROM strings ORDER BY id LIMIT 1")
                ->execute(['Tástáil', 'health-check', 'nóta']);
        },
        'vote' => function ($pdo) {
            $id = $pdo->query('SELECT MAX(id) FROM translations')->fetchColumn();
            $pdo->prepare('INSERT INTO votes (translation_id, voter, vote) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE vote = VALUES(vote)')
                ->execute([$id, 'health-check', 1]);
        },
        'add_note' => function ($pdo) {
            $pdo->prepare('INSERT INTO notes (string_id, author, text) SELECT id, ?, ? FROM strings ORDER BY id LIMIT 1')
                ->execute(['health-check', 'test']);
        },
    ];
    foreach ($tests as $name => $fn) {
        try { $pdo->beginTransaction(); $fn($pdo); $r['write_tests'][$name] = 'ok'; }
        catch (Throwable $e) { $r['write_tests'][$name] = 'FAILED: ' . $e->getMessage(); }
        finally { if ($pdo->inTransaction()) $pdo->rollBack(); }
    }
    $js = fetch_source();
    $r['translations_source'] = $js === null ? 'FAILED to read ' . TRANSLATIONS_SOURCE : count(parse_translations($js)) . ' keys read from ' . TRANSLATIONS_SOURCE;
    out($r);
}

// Delete (or restore) a translation or a note. Rows are only marked as
// deleted, so "Undo" works and nothing is lost for good. Votes on a deleted
// translation are kept but no longer count.
function action_delete(): void {
    check_write_request();
    $b = body();
    $type = $b['type'] ?? '';
    $id = (int)($b['id'] ?? 0);
    $name = clean_name($b['name'] ?? '');
    $restore = !empty($b['restore']);
    if ($name === '') fail('Your name is required (up to 80 characters).');
    $table = $type === 'translation' ? 'translations' : ($type === 'note' ? 'notes' : null);
    if (!$table) fail('Invalid type.');
    retrying(function () use ($table, $id, $name, $restore) {
        $st = $restore
            ? db()->prepare("UPDATE `$table` SET deleted_at = NULL, deleted_by = NULL WHERE id = ?")
            : db()->prepare("UPDATE `$table` SET deleted_at = UTC_TIMESTAMP(), deleted_by = ? WHERE id = ? AND deleted_at IS NULL");
        $st->execute($restore ? [$id] : [$name, $id]);
    });
    out(['ok' => true]);
}

// Upgrades an older database in place (run automatically, once).
//   v2: deleted_at/deleted_by on translations and notes, and the Irish that
//       was imported from translations.js is credited to Claude (AI).
function ensure_schema(): void {
    $pdo = db();
    $v = (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema_version'")->fetchColumn() ?: 1);
    if ($v >= 2) return;
    if ((int)$pdo->query("SELECT GET_LOCK('far_translate_schema', 10)")->fetchColumn() !== 1) return;
    try {
        $v = (int)($pdo->query("SELECT v FROM meta WHERE k = 'schema_version'")->fetchColumn() ?: 1);
        if ($v >= 2) return;
        foreach (['translations', 'notes'] as $t) {
            if (!$pdo->query("SHOW COLUMNS FROM `$t` LIKE 'deleted_at'")->fetch()) {
                $pdo->exec("ALTER TABLE `$t` ADD COLUMN deleted_at DATETIME NULL, ADD COLUMN deleted_by VARCHAR(80) NULL");
            }
        }
        $pdo->exec("UPDATE translations SET translator = 'Claude (AI)' WHERE source = 'ai' AND translator = 'translations.js'");
        $pdo->exec("INSERT INTO meta (k, v) VALUES ('schema_version', '2') ON DUPLICATE KEY UPDATE v = '2'");
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('far_translate_schema')");
    }
}

// ---- router --------------------------------------------------------------------
try {
    $action = $_GET['action'] ?? '';
    if ($action !== 'health') ensure_schema();
    switch ($action) {
        case 'list':            action_list(); break;
        case 'export':          action_export(); break;
        case 'add_translation': action_add_translation(); break;
        case 'add_note':        action_add_note(); break;
        case 'vote':            action_vote(); break;
        case 'delete':          action_delete(); break;
        case 'health':          action_health(); break;
        default:                fail('Unknown action', 404);
    }
} catch (Throwable $e) {
    error_log('[far-translate] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = defined('DEBUG_ERRORS') && DEBUG_ERRORS;
    fail($debug ? 'Server error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')' : 'Server error. Please try again.', 500);
}
