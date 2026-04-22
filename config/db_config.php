<?php
/**
 * Datenbank-Konfiguration.
 *
 * Credentials kommen aus der .env-Datei im Projekt-Root (nicht in Git).
 * .env.example listet die erwarteten Keys.
 */

require_once __DIR__ . '/../includes/env.php';
load_env(__DIR__ . '/../.env');

define('DB_SERVER', env('DB_SERVER', '192.168.10.144'));
define('DB_USERNAME', env('DB_USERNAME', 'teci_viewonly'));
define('DB_PASSWORD', env('DB_PASSWORD', ''));
define('DB_NAME', env('DB_NAME', 'crone_log'));

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Verbindung fehlgeschlagen: " . $conn->connect_error);
}

$conn->set_charset("utf8");

/**
 * Sichere Query-Ausfuehrung.
 */
function execute_query($conn, $sql) {
    $result = $conn->query($sql);
    if ($result === false) {
        error_log("SQL Error: " . $conn->error . " | Query: " . substr($sql, 0, 200));
        return false;
    }
    return $result;
}
