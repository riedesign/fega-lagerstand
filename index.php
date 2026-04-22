<?php
/**
 * Router / Entry Point
 */
require_once __DIR__ . '/includes/env.php';
load_env(__DIR__ . '/.env');

$app_env = env('APP_ENV', 'production');
error_reporting(E_ALL);
// In Produktion niemals Stacktraces im Browser zeigen — nur ins Log.
ini_set('display_errors', $app_env === 'development' ? '1' : '0');
ini_set('log_errors', '1');

// Auth: JWT-Cookie vom Rieste-Auth-Portal validieren
require_once __DIR__ . '/includes/auth.php';
if (!fega_auth_disabled()) {
    $user = fega_require_login();
} else {
    $user = ['id' => 0, 'username' => 'dev', 'role' => 'admin', 'display_name' => 'Local Dev', 'email' => ''];
}

$page = $_GET['page'] ?? 'markt';

// Alias fuer alte Bookmarks
if ($page === 'kpi') $page = 'dispo';

$allowed_pages = ['markt', 'produktdetail', 'dispo', 'vergleich'];

if (!in_array($page, $allowed_pages)) {
    $page = 'markt';
}

require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/includes/functions.php';

// Globale Parameter
$time_period = resolve_time_period($_GET['time_period'] ?? '4_weeks');

include __DIR__ . '/views/header.php';
include __DIR__ . "/views/{$page}.php";
include __DIR__ . '/views/footer.php';

$conn->close();
