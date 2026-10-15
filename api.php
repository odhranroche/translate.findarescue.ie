<?php
// ==========================================================================
// Translation portal: JSON API (entry point)
//   GET  api.php?action=config             language + project settings for the page
//   GET  api.php?action=list[&resync=1]    all strings, translations, votes, notes
//   GET  api.php?action=export             target-language block built from the votes
//   GET  api.php?action=health             setup check
//   POST api.php?action=add_translation    {string_id, text, name, note?}
//   POST api.php?action=add_note           {string_id, text, name}
//   POST api.php?action=vote               {translation_id, name, vote: 1|-1|0}
//   POST api.php?action=delete             {type: translation|note, id, name, restore?}
// Every write is one short autocommit statement, and the sync runs in a
// single transaction behind a named lock, so requests don't deadlock.
//
// This file only routes requests. The work is done in core/, and everything
// specific to one language or project is in languages/<PORTAL_LANGUAGE>/.
// ==========================================================================

declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/core/portal.php';
require __DIR__ . '/core/http.php';
require __DIR__ . '/core/db.php';
require __DIR__ . '/core/source.php';
require __DIR__ . '/core/sync.php';
require __DIR__ . '/core/actions.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

try {
    $action = $_GET['action'] ?? '';
    portal();   // fail early, with a clear message, if the language isn't set up
    if ($action !== 'health' && $action !== 'config') ensure_schema();
    switch ($action) {
        case 'config':          action_config(); break;
        case 'list':            action_list(); break;
        case 'export':          action_export(); break;
        case 'add_translation': action_add_translation(); break;
        case 'add_note':        action_add_note(); break;
        case 'vote':            action_vote(); break;
        case 'delete':          action_delete(); break;
        case 'health':          action_health(); break;
        default:                fail('Unknown action', 404);
    }
} catch (PortalConfigError $e) {
    fail($e->getMessage(), 500);
} catch (Throwable $e) {
    error_log('[translate-portal] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $debug = defined('DEBUG_ERRORS') && DEBUG_ERRORS;
    fail($debug ? 'Server error: ' . $e->getMessage() . ' (line ' . $e->getLine() . ')' : 'Server error. Please try again.', 500);
}
