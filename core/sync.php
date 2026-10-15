<?php
// Translation portal: keeping the database in line with the translations file.

declare(strict_types=1);

// Bring the database in line with the translations file. Returns a status string.
//
// Column naming: for historical reasons the `strings` table calls the target
// language's current value `file_ga` / `file_ga_todo`, whatever language the
// portal serves. `str_key`, `en_text` and `prev_en_text` likewise hold the
// BASE language text. Renaming them would need a migration of live data for no
// functional gain, so the names are kept.
function sync(bool $force): string {
    $pdo = db();
    $last = (int)($pdo->query("SELECT v FROM meta WHERE k = 'last_sync'")->fetchColumn() ?: 0);
    if (!$force && time() - $last < SYNC_INTERVAL) return 'skipped';

    // Only one sync at a time; if another request is syncing, don't wait.
    $lock = lock_name('sync');
    if ((int)$pdo->query("SELECT GET_LOCK('$lock', 0)")->fetchColumn() !== 1) return 'busy';
    try {
        $js = fetch_source();
        if ($js === null) return 'fetch_failed';
        $rows = parse_translations($js);
        if (count($rows) === 0) return 'parse_failed';   // never retire everything on a bad read

        $aiLabel = portal()['ai']['label'];
        $seed = portal_ai_seed();

        retrying(function () use ($pdo, $rows, $aiLabel, $seed) {
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
                    $up->execute([$k, $r['section'], $r['order'], $r['base'], $r['target'], $r['todo'] ? 1 : 0, $sid]);
                }
                $st = $pdo->prepare('UPDATE strings SET active = 0 WHERE seen_sync <> ? AND active = 1');
                $st->execute([$sid]);

                // Seed text shipped with the language (languages/<id>/ai_seed.json).
                // INSERT IGNORE: rows already in the table (including edits made
                // there by hand) are never overwritten.
                if ($seed) {
                    $ins = $pdo->prepare('INSERT IGNORE INTO ai_seed (str_key, text) VALUES (?, ?)');
                    foreach ($seed as $k => $text) $ins->execute([$k, $text]);
                }

                // AI column: the file's target text where it's real, otherwise the seeded AI text.
                $pdo->prepare(
                    "INSERT INTO translations (string_id, source, text, translator, en_snapshot)
                     SELECT s.id, 'ai', s.file_ga, ?, s.en_text FROM strings s
                     WHERE s.file_ga_todo = 0 AND s.file_ga IS NOT NULL AND s.file_ga <> ''
                       AND NOT EXISTS (SELECT 1 FROM translations t WHERE t.string_id = s.id AND t.source = 'ai')"
                )->execute([$aiLabel]);
                $pdo->prepare(
                    "INSERT INTO translations (string_id, source, text, translator, en_snapshot)
                     SELECT s.id, 'ai', a.text, ?, s.en_text FROM strings s JOIN ai_seed a ON a.str_key = s.str_key
                     WHERE s.file_ga_todo = 1
                       AND NOT EXISTS (SELECT 1 FROM translations t WHERE t.string_id = s.id AND t.source = 'ai')"
                )->execute([$aiLabel]);
                $pdo->exec("INSERT INTO meta (k, v) VALUES ('last_sync', UNIX_TIMESTAMP()) ON DUPLICATE KEY UPDATE v = UNIX_TIMESTAMP()");
                $pdo->commit();
            } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        });
        return 'synced';
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('$lock')");
    }
}
