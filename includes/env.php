<?php
/**
 * Minimaler .env-Loader — ohne Composer/dotenv-Abhaengigkeit.
 *
 * Liest einfache KEY=VALUE-Zeilen aus einer .env-Datei im Projekt-Root
 * und legt sie in $_ENV + getenv() ab. Leerzeilen und Kommentare
 * (beginnen mit ; oder #) werden ignoriert. Werte werden nicht geparst
 * (kein Quoting, kein Escaping) — fuer Projekte mit trivialer Config
 * vollkommen ausreichend.
 *
 * Usage:
 *   require_once __DIR__ . '/env.php';
 *   load_env(__DIR__ . '/../.env');
 *   $pw = env('DB_PASSWORD', '');
 */

function load_env(string $path): void {
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || $line[0] === ';') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Doppelte Anfuehrungszeichen um den Wert optional strippen
        if (strlen($value) >= 2
            && ($value[0] === '"' || $value[0] === "'")
            && $value[strlen($value) - 1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

function env(string $key, ?string $default = null): ?string {
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }
    $val = getenv($key);
    return $val === false ? $default : $val;
}
