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

$page = $_GET['page'] ?? 'kpi';
$allowed_pages = ['kpi', 'produktdetail', 'vergleich'];

if (!in_array($page, $allowed_pages)) {
    $page = 'kpi';
}

require_once __DIR__ . '/config/db_config.php';
require_once __DIR__ . '/config/app_config.php';
require_once __DIR__ . '/includes/functions.php';

// Globale Parameter
$time_period = $_GET['time_period'] ?? '3_weeks';
if (!isset($TIME_PERIODS[$time_period])) {
    $time_period = '3_weeks';
}

include __DIR__ . '/views/header.php';
include __DIR__ . "/views/{$page}.php";
include __DIR__ . '/views/footer.php';

$conn->close();
