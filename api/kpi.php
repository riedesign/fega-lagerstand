<?php
/**
 * API-Endpunkt: KPI-Dashboard Daten
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/kpi.php';

$time_period = $_GET['time_period'] ?? '3_weeks';
if (!isset($TIME_PERIODS[$time_period])) {
    $time_period = '3_weeks';
}

$data = get_kpi_overview($conn, $time_period);

echo json_encode($data);

$conn->close();
