<?php
/**
 * API-Endpunkt: Markt-Dashboard Daten (Sales-Sicht, alle Bloecke in einem Call)
 */
require_once __DIR__ . '/../includes/auth.php';
if (!fega_auth_disabled()) { fega_require_api(); }

header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/markt.php';

$time_period = resolve_time_period($_GET['time_period'] ?? '4_weeks');

$data = get_markt_data($conn, $time_period);

echo json_encode($data);

$conn->close();
