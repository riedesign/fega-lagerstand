<?php
/**
 * API-Endpunkt: Produktdetail
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_config.php';
require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/queries/produktdetail.php';

$time_period = $_GET['time_period'] ?? '3_weeks';
if (!isset($TIME_PERIODS[$time_period])) {
    $time_period = '3_weeks';
}

$han = $_GET['han'] ?? '';

if (empty($han)) {
    echo json_encode(['error' => 'Kein Artikel (HAN) angegeben.']);
    $conn->close();
    exit;
}

$data = get_produkt_detail($conn, $han, $time_period);

if ($data === null) {
    echo json_encode(['error' => 'Keine Daten fuer diesen Artikel gefunden.']);
} else {
    echo json_encode($data);
}

$conn->close();
