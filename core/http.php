<?php
// Translation portal: JSON responses, input cleaning and write protection.

declare(strict_types=1);

function out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void { out(['ok' => false, 'error' => $msg], $code); }

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
// Values the page sends in X-Requested-With. "far-translate" is what the page
// sent before the portal was made reusable; it is accepted so tabs that were
// open during the upgrade keep saving. Delete it once nobody has an old tab.
const WRITE_MARKERS = ['translate-portal', 'far-translate'];

function check_write_request(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required', 405);
    // Browsers can't send this header cross-site without a CORS preflight,
    // which we never approve, so it stops forged form posts from other sites.
    if (!in_array($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', WRITE_MARKERS, true)) fail('Bad request', 403);
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
