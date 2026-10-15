<?php
// Translation portal: settings. Copy this file to config.php and fill it in.
// config.php holds your database password: it is not committed to git and
// .htaccess blocks it from being downloaded. It prints nothing if opened in a
// browser.

// Which language this portal serves: the name of a folder inside languages/.
// To add a language, copy languages/_template to languages/<name> and edit it.
define('PORTAL_LANGUAGE', 'ga');

define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');

// Where to read the project's translations file from. Use a full URL, or an
// absolute server path if this tool is on the same hosting account as the
// site, e.g.
//   '/home/youraccount/domains/example.com/public_html/translations.js'
define('TRANSLATIONS_SOURCE', 'https://example.com/translations.js');

// Re-read the source file at most this often (seconds). The "Resync now"
// button skips this wait.
define('SYNC_INTERVAL', 300);

// true = show the real error message on the page when something fails.
// Handy while setting up; set to false once everything works.
define('DEBUG_ERRORS', true);

// Writes allowed per IP address per minute.
define('RATE_LIMIT_PER_MIN', 60);
