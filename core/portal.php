<?php
// ==========================================================================
// Translation portal: language configuration.
//
// One deployment of this portal serves ONE language. Which one is chosen by
// PORTAL_LANGUAGE in config.php: the name of a folder inside languages/.
// Everything language- or project-specific lives in that folder's
// language.json (see languages/README.md), so the rest of the code is generic.
// ==========================================================================

declare(strict_types=1);

/** A setup mistake. Its message never contains secrets, so it is always shown. */
class PortalConfigError extends RuntimeException {}

/** Values used when language.json leaves something out. */
function portal_defaults(): array {
    return [
        'project'        => ['name' => '', 'url' => ''],
        'language'       => ['code' => '', 'name' => '', 'native_name' => '', 'logo' => ''],
        'base_language'  => ['code' => 'en', 'name' => 'English'],
        'source'         => ['file_name' => 'translations.js', 'base_block' => '', 'target_block' => '', 'todo_marker' => ''],
        'export'         => ['file_name' => ''],
        'ai'             => ['label' => 'AI'],
        'ui_locale'      => 'en',
        'storage_prefix' => '',
        'theme'          => ['color' => '#0b6e4f', 'light' => new stdClass, 'dark' => new stdClass],
        'section_strip'  => ['\\s*\\(.*\\)\\s*$'],
        'ui'             => new stdClass,
    ];
}

/** Recursive merge where lists (and anything non-associative) are replaced, not combined. */
function portal_merge(array $base, array $over): array {
    foreach ($over as $k => $v) {
        $isMap = is_array($v) && $v !== [] && array_keys($v) !== range(0, count($v) - 1);
        if ($isMap && isset($base[$k]) && is_array($base[$k])) $base[$k] = portal_merge($base[$k], $v);
        else $base[$k] = $v;
    }
    return $base;
}

/** The id of the language this deployment serves (a folder name inside languages/). */
function portal_language_id(): string {
    // Older config.php files predate PORTAL_LANGUAGE; they were all the Irish portal.
    $id = defined('PORTAL_LANGUAGE') ? (string)PORTAL_LANGUAGE : 'ga';
    if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $id)) {
        throw new PortalConfigError(
            'PORTAL_LANGUAGE in config.php must be the name of a folder inside languages/ '
            . '(letters, digits, - or _, not starting with _). To add a language, copy languages/_template first.'
        );
    }
    return $id;
}

/** The merged, validated configuration. Cached for the request. */
function portal(): array {
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $id   = portal_language_id();
    $dir  = __DIR__ . '/../languages/' . $id;
    $file = $dir . '/language.json';
    if (!is_readable($file)) {
        throw new PortalConfigError("Language \"$id\" not found: expected languages/$id/language.json. Check PORTAL_LANGUAGE in config.php.");
    }
    $raw = json_decode((string)file_get_contents($file), true);
    if (!is_array($raw)) {
        throw new PortalConfigError("languages/$id/language.json is not valid JSON (" . json_last_error_msg() . ').');
    }
    $c = portal_merge(portal_defaults(), $raw);

    foreach (['project.name' => $c['project']['name'], 'language.code' => $c['language']['code'], 'language.name' => $c['language']['name']] as $path => $v) {
        if (!is_string($v) || trim($v) === '') throw new PortalConfigError("languages/$id/language.json: \"$path\" is required.");
    }

    // Fill in the values that can be worked out from the others.
    $code = $c['language']['code'];
    $c['id'] = $id;
    if ($c['language']['native_name'] === '') $c['language']['native_name'] = $c['language']['name'];
    if ($c['language']['logo'] === '')        $c['language']['logo']        = $code;
    if ($c['source']['base_block'] === '')    $c['source']['base_block']    = $c['base_language']['code'];
    if ($c['source']['target_block'] === '')  $c['source']['target_block']  = $code;
    if ($c['source']['todo_marker'] === '')   $c['source']['todo_marker']   = "TODO ($code)";
    if ($c['export']['file_name'] === '')     $c['export']['file_name']     = "translations-$code.js";
    if ($c['storage_prefix'] === '')          $c['storage_prefix']          = "tp_{$id}_";

    foreach (['source.base_block' => $c['source']['base_block'], 'source.target_block' => $c['source']['target_block']] as $path => $v) {
        if (!is_string($v) || !preg_match('/^[A-Za-z0-9_$-]+$/', $v)) throw new PortalConfigError("languages/$id/language.json: \"$path\" must be a plain name such as en or pt-BR.");
    }
    if ($c['source']['base_block'] === $c['source']['target_block']) {
        throw new PortalConfigError("languages/$id/language.json: the base and target blocks must differ.");
    }

    $c['_dir'] = $dir;   // private: never sent to the browser
    return $cfg = $c;
}

/** The part of the configuration the browser needs (everything except private "_" keys). */
function portal_public(): array {
    return array_filter(portal(), fn($k) => $k[0] !== '_', ARRAY_FILTER_USE_KEY);
}

/** AI translations to pre-load for keys whose target text is still a placeholder: [key => text]. */
function portal_ai_seed(): array {
    $file = portal()['_dir'] . '/ai_seed.json';
    if (!is_readable($file)) return [];
    $j = json_decode((string)file_get_contents($file), true);
    if (!is_array($j)) return [];
    $out = [];
    foreach ($j as $k => $v) {
        if (is_string($k) && is_string($v) && $v !== '' && strlen($k) <= 100 && preg_match('/^[A-Za-z_$][\w$]*$/', $k)) $out[$k] = $v;
    }
    return $out;
}
