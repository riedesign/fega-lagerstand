<?php
/**
 * Auth-Guard fuer Fega-Lagerstand.
 *
 * Validiert das `rieste_token` JWT-Cookie vom zentralen Auth-Portal
 * (auth.rieste.org) und stellt sicher, dass der User Zugriff auf
 * diese App hat. Bei fehlendem oder ungueltigem Cookie → Redirect
 * zum Portal-Login.
 *
 * Usage in index.php ganz oben:
 *   require_once __DIR__ . '/includes/auth.php';
 *   $user = fega_require_login();
 *
 * In api/*.php (fuer JSON-Responses mit 401 statt Redirect):
 *   require_once __DIR__ . '/../includes/auth.php';
 *   $user = fega_require_api();
 *
 * Kein Composer noetig — HS256 in 30 Zeilen Vanilla-PHP.
 *
 * App-Slug im JWT: konfigurierbar via ENV `AUTH_APP_SLUG`, default
 * 'fega'. Im Auth-Portal muss eine App mit diesem Slug + User-
 * Freigaben existieren.
 */

require_once __DIR__ . '/env.php';

const FEGA_AUTH_PORTAL = 'https://auth.rieste.org';
const FEGA_AUTH_COOKIE = 'rieste_token';

/**
 * Base64URL-Decode ohne Padding-Korrekturen.
 */
function fega_b64url_decode(string $s): string {
    $pad = 4 - (strlen($s) % 4);
    if ($pad < 4) {
        $s .= str_repeat('=', $pad);
    }
    return base64_decode(strtr($s, '-_', '+/'));
}

/**
 * Validiert einen HS256-JWT. Gibt Payload-Array zurueck oder null.
 */
function fega_jwt_decode(string $token, string $secret): ?array {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h64, $p64, $s64] = $parts;

    $header = json_decode(fega_b64url_decode($h64), true);
    $payload = json_decode(fega_b64url_decode($p64), true);
    $signature = fega_b64url_decode($s64);
    if (!is_array($header) || !is_array($payload) || $signature === false) return null;
    if (($header['alg'] ?? '') !== 'HS256') return null;

    $expected = hash_hmac('sha256', "$h64.$p64", $secret, true);
    if (!hash_equals($expected, $signature)) return null;

    $now = time();
    if (isset($payload['exp']) && $payload['exp'] < $now - 60) return null;   // 60s leeway
    if (isset($payload['nbf']) && $payload['nbf'] > $now + 60) return null;

    return $payload;
}

/**
 * Liest + validiert das Cookie. Gibt bei Erfolg das User-Array zurueck,
 * sonst null.
 */
function fega_current_user(): ?array {
    $secret = env('JWT_SECRET_KEY', '');
    if ($secret === '' || !isset($_COOKIE[FEGA_AUTH_COOKIE])) return null;

    $payload = fega_jwt_decode($_COOKIE[FEGA_AUTH_COOKIE], $secret);
    if ($payload === null) return null;

    $slug = env('AUTH_APP_SLUG', 'fega');
    $apps = $payload['apps'] ?? [];
    // Akzeptiere sowohl 'fega' als auch 'fega-lagerstand' als Slug.
    $app_access = $apps[$slug] ?? $apps['fega-lagerstand'] ?? null;
    if (!$app_access) return null;

    return [
        'id'           => $payload['sub'] ?? null,
        'username'     => $payload['username'] ?? '',
        'email'        => $payload['email'] ?? '',
        'display_name' => $payload['display_name'] ?? $payload['username'] ?? '',
        'role'         => $app_access['role'] ?? 'viewer',
    ];
}

/**
 * Fuer HTML-Requests: Login-User oder Redirect zum Portal.
 */
function fega_require_login(): array {
    $user = fega_current_user();
    if ($user !== null) return $user;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri  = $_SERVER['REQUEST_URI'] ?? '/';
    $next = urlencode("$scheme://$host$uri");

    header('Location: ' . FEGA_AUTH_PORTAL . "/login?next=$next");
    exit;
}

/**
 * Fuer JSON-API-Requests: User oder 401 + JSON-Body.
 */
function fega_require_api(): array {
    $user = fega_current_user();
    if ($user !== null) return $user;

    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Nicht angemeldet',
        'login_url' => FEGA_AUTH_PORTAL . '/login',
    ]);
    exit;
}

/**
 * Optional: Auth komplett deaktivieren per ENV (fuer lokale Entwicklung
 * ohne Auth-Portal). Nur in development nutzen!
 */
function fega_auth_disabled(): bool {
    return env('AUTH_DISABLED', '0') === '1';
}
