<?php
/**
 * API-Endpunkt: Dispo-Dashboard Daten (nur Eigenprodukte)
 */
require_once __DIR__ . '/../includes/auth.php';
if (!fega_auth_disabled()) { fega_require_api(); }

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/dispo.php';

$time_period = resolve_time_period($_GET['time_period'] ?? '4_weeks');

$data = get_dispo_overview($conn, $time_period);

echo json_encode($data);

$conn->close();
