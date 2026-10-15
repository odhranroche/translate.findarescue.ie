<?php
// Translation portal: reading and parsing the project's translations file.
//
// The file is a JavaScript object with one block per language, e.g.
//     en: {
//       // Section name
//       homeTitle: "Hello",
//     },
//     ga: {
//       homeTitle: "Dia duit",
//     },
// The block names, the marker for untranslated placeholders and the file's
// name all come from language.json ("source").

declare(strict_types=1);

function fetch_source(): ?string {
    $src = TRANSLATIONS_SOURCE;
    if (!preg_match('#^https?://#i', $src)) return is_readable($src) ? file_get_contents($src) : null;
    $url = $src . (strpos($src, '?') === false ? '?' : '&') . 'nocache=' . time();
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10, CURLOPT_USERAGENT => 'translate-portal/1.0']);
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

// Returns [key => [base, target, todo, section, order]]. Supports one-line
// `key: "text",` entries inside the base-language and target-language blocks.
function parse_translations(string $js): array {
    $src    = portal()['source'];
    $baseB  = $src['base_block'];
    $targetB = $src['target_block'];
    $marker = $src['todo_marker'];

    $lines = preg_split('/\R/', $js);
    $block = null; $section = null; $todoBlock = false; $order = 0;
    $base = []; $target = [];
    $open = '/^\s*["\']?(' . preg_quote($baseB, '/') . '|' . preg_quote($targetB, '/') . ')["\']?\s*:\s*\{\s*$/';
    $re = '/^\s*([A-Za-z_$][\w$]*)\s*:\s*("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')\s*,?\s*(\/\/.*)?$/';
    foreach ($lines as $line) {
        if (preg_match($open, $line, $m)) { $block = $m[1] === $baseB ? 'base' : 'target'; $section = null; $todoBlock = false; continue; }
        if ($block === null) continue;
        if (preg_match('/^\s*\}\s*,?\s*$/', $line)) { $block = null; continue; }
        if (trim($line) === '') { $todoBlock = false; continue; }
        if (preg_match('#^\s*//\s*(.*)$#', $line, $m)) {
            if ($block === 'base') $section = trim($m[1]);
            $todoBlock = stripos($m[1], $marker) !== false;
            continue;
        }
        if (!preg_match($re, $line, $m)) continue;
        $val = js_string($m[2]);
        if ($val === null) continue;
        if ($block === 'base') { $base[$m[1]] = ['text' => $val, 'section' => $section, 'order' => ++$order]; }
        else {
            $target[$m[1]] = ['text' => $val, 'todo_inline' => isset($m[3]) && stripos($m[3], $marker) !== false, 'todo_block' => $todoBlock];
            // A "// TODO" comment covers the run of placeholders (target text
            // identical to the base text) right after it; the first real
            // translation ends that run.
            if (!isset($base[$m[1]]) || $base[$m[1]]['text'] !== $val) $todoBlock = false;
        }
    }
    $out = [];
    foreach ($base as $k => $e) {
        $g = $target[$k] ?? null;
        $todo = $g === null || $g['todo_inline'] || ($g['todo_block'] && $g['text'] === $e['text']);
        $out[$k] = ['base' => $e['text'], 'target' => $g['text'] ?? null, 'todo' => $todo, 'section' => $e['section'], 'order' => $e['order']];
    }
    return $out;
}
