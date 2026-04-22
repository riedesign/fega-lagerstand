<?php
/**
 * API-Endpunkt: Verkaufsindex Eigen vs. Fremd
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/verkaufsindex.php';

$time_period = $_GET['time_period'] ?? '3_weeks';
if (!isset($TIME_PERIODS[$time_period])) {
    $time_period = '3_weeks';
}
$aggregation = $_GET['aggregation'] ?? 'day';
if (!in_array($aggregation, ['day', 'week', 'month'])) {
    $aggregation = 'day';
}

$data = get_verkaufsindex_data($conn, $time_period, $aggregation);

echo json_encode($data);

$conn->close();
