<?php
/**
 * API-Endpunkt: Kategorie-/Marken-Vergleich
 */
require_once __DIR__ . '/../includes/auth.php';
if (!fega_auth_disabled()) { fega_require_api(); }

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/kategorien.php';

$time_period = $_GET['time_period'] ?? '3_weeks';
if (!isset($TIME_PERIODS[$time_period])) {
    $time_period = '3_weeks';
}
$aggregation = $_GET['aggregation'] ?? 'day';
if (!in_array($aggregation, ['day', 'week', 'month'])) {
    $aggregation = 'day';
}

$data = get_marken_vergleich($conn, $time_period, $aggregation);

echo json_encode($data);

$conn->close();
