<?php
// Translation portal: the API actions (see api.php for the list).

declare(strict_types=1);

const ISO = "'%Y-%m-%dT%H:%i:%sZ'";

// The language settings the page needs (names, labels, theme, UI overrides).
function action_config(): void {
    out(['ok' => true, 'config' => portal_public()]);
}

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

// Build the target-language block, e.g. `ga: { … }`. For each key it uses the
// accepted translation: the one with the highest net score above zero (latest
// wins ties); otherwise it keeps the file's current target text, or the base
// text marked with the TODO marker.
function action_export(): void {
    $pdo = db();
    $src = portal()['source'];
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
    $blockName = preg_match('/^[A-Za-z_$][\w$]*$/', $src['target_block']) ? $src['target_block'] : $enc($src['target_block']);
    $marker = $src['todo_marker'];
    $o = "  $blockName: {\n"; $sec = null; $first = true;
    foreach ($strings as $s) {
        if ($s['section'] !== $sec) { $sec = $s['section']; if (!$first) $o .= "\n"; if ($sec) $o .= "    // $sec\n"; }
        $first = false;
        if (isset($best[$s['id']]))                            $o .= "    {$s['str_key']}: " . $enc($best[$s['id']]) . ",\n";
        elseif (!$s['file_ga_todo'] && $s['file_ga'] !== null) $o .= "    {$s['str_key']}: " . $enc($s['file_ga']) . ",\n";
        else                                                   $o .= "    {$s['str_key']}: " . $enc($s['en_text']) . ", // $marker\n";
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
    $r = ['php' => PHP_VERSION, 'language' => portal()['id'], 'extensions' => [], 'db' => [], 'tables' => [], 'write_tests' => []];
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
                ->execute(['Tástáil', 'health-check', 'nóta']);   // accented text proves utf8mb4 works
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
