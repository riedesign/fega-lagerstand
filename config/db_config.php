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

/**
 * Lazy-initialisierte PDO-Verbindung zur JTL-MSSQL-Datenbank (eazybusiness).
 *
 * Gibt `null` zurueck wenn
 *   - die JTL_MSSQL_* Env-Vars nicht gesetzt sind, oder
 *   - der pdo_dblib-Treiber fehlt, oder
 *   - der Verbindungsaufbau scheitert.
 *
 * So kann die Dispo-Seite den Rieste-Bestand optional einblenden, ohne
 * dass fehlende Config die ganze App lahmlegt.
 */
function get_jtl_mssql_conn() {
    static $conn = null;
    static $tried = false;
    if ($tried) return $conn;
    $tried = true;

    $host = env('JTL_MSSQL_HOST', '');
    $user = env('JTL_MSSQL_USER', '');
    $pass = env('JTL_MSSQL_PASSWORD', '');
    $db   = env('JTL_MSSQL_DATABASE', 'eazybusiness');
    $port = env('JTL_MSSQL_PORT', '1433');

    if ($host === '' || $user === '' || $pass === '') {
        return null;
    }
    if (!in_array('dblib', PDO::getAvailableDrivers(), true)) {
        error_log('JTL MSSQL nicht verfuegbar: pdo_dblib fehlt.');
        return null;
    }

    try {
        $dsn = "dblib:host={$host}:{$port};dbname={$db};charset=UTF-8";
        $conn = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        return $conn;
    } catch (Throwable $e) {
        error_log('JTL MSSQL Connect fehlgeschlagen: ' . $e->getMessage());
        $conn = null;
        return null;
    }
}
