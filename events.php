<?php
/**
 * EntdeckerWeine — Termin-Endpunkt für die Startseite
 *
 * Liest die Veranstaltungen live aus dem Shop (shop.entdeckerweine.de), filtert
 * auf zukünftige Termine und liefert sie als JSON. Der Abruf läuft serverseitig,
 * dadurch entfällt das CORS-Problem des Shops.
 *
 * Aufruf:  /events.php?limit=3
 * Antwort: {"generated":"…","source":"live|cache","count":3,"events":[…]}
 *
 * Der Shop wird höchstens alle EW_CACHE_TTL Sekunden angefragt; dazwischen
 * kommt die Antwort aus dem Cache. Ist der Shop nicht erreichbar, wird der
 * letzte bekannte Stand ausgeliefert (stale-if-error).
 */
declare(strict_types=1);

require __DIR__ . '/ew-events-lib.php';

const EW_CACHE_TTL     = 900;   // 15 Minuten
const EW_HTTP_TIMEOUT  = 8;     // Sekunden
const EW_DEFAULT_LIMIT = 3;
const EW_MAX_LIMIT     = 25;

/**
 * Pfad der Cache-Datei.
 *
 * Liegt in einem eigenen Unterverzeichnis mit installationsabhaengigem Namen und
 * Rechten 0700. Auf Shared Hosting ist das Systemtemp-Verzeichnis von mehreren
 * Kunden nutzbar; ein fester Dateiname waere dort fremdbeschreibbar (Cache-Poisoning)
 * bzw. per Symlink umlenkbar. Beides ist damit ausgeschlossen.
 */
function ew_cache_file(): ?string
{
    $base = getenv('EW_CACHE_DIR') ?: sys_get_temp_dir();
    $dir  = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
          . 'ew-cache-' . substr(hash('sha256', __DIR__), 0, 16);

    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return null;                      // kein Cache moeglich -> es wird live geholt
    }
    if (is_link($dir) || !is_writable($dir)) {
        return null;
    }

    $file = $dir . DIRECTORY_SEPARATOR . 'events.json';
    if (is_link($file)) {                  // untergeschobener Symlink -> nicht benutzen
        @unlink($file);
        return null;
    }
    return $file;
}

/** Holt die Shop-Seite. EW_EVENTS_SOURCE (Datei oder URL) erlaubt Tests ohne Netz. */
function ew_fetch_html(): ?string
{
    $source = getenv('EW_EVENTS_SOURCE') ?: EW_EVENTS_URL;

    if (!preg_match('#^https?://#i', $source)) {
        $local = @file_get_contents($source);
        return is_string($local) && $local !== '' ? $local : null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($source);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => EW_HTTP_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT      => 'entdeckerweine.de Terminabruf/1.0',
            CURLOPT_HTTPHEADER     => ['Accept: text/html'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($body) && $body !== '' && $code >= 200 && $code < 300) {
            return $body;
        }
        return null;
    }

    $context = stream_context_create(['http' => [
        'timeout' => EW_HTTP_TIMEOUT,
        'header'  => "User-Agent: entdeckerweine.de Terminabruf/1.0\r\nAccept: text/html\r\n",
    ]]);
    $body = @file_get_contents($source, false, $context);
    return is_string($body) && $body !== '' ? $body : null;
}

/** Termine holen — aus dem Cache, sonst frisch vom Shop. */
function ew_load_events(bool &$fromCache): array
{
    $cacheFile = ew_cache_file();
    $cached = null;

    if ($cacheFile !== null && is_readable($cacheFile)) {
        $raw = @file_get_contents($cacheFile);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded) && isset($decoded['events']) && is_array($decoded['events'])) {
            $cached = $decoded;
            $age = time() - (int) ($decoded['time'] ?? 0);
            if ($age >= 0 && $age < EW_CACHE_TTL) {
                $fromCache = true;
                return $decoded['events'];
            }
        }
    }

    $html = ew_fetch_html();
    if ($html !== null) {
        $events = ew_parse_events($html);
        if (count($events) > 0) {
            if ($cacheFile !== null) {
                @file_put_contents(
                    $cacheFile,
                    json_encode(['time' => time(), 'events' => $events], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                );
                @chmod($cacheFile, 0600);
            }
            $fromCache = false;
            return $events;
        }
    }

    if ($cached !== null) {          // Shop nicht erreichbar → letzter bekannter Stand
        $fromCache = true;
        return $cached['events'];
    }

    $fromCache = false;
    return [];
}

/* ---------- Antwort ---------- */

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : EW_DEFAULT_LIMIT;
if ($limit < 1) { $limit = EW_DEFAULT_LIMIT; }
if ($limit > EW_MAX_LIMIT) { $limit = EW_MAX_LIMIT; }

$fromCache = false;
$all = ew_load_events($fromCache);
$now = new DateTimeImmutable('now', new DateTimeZone(EW_TIMEZONE));
$upcoming = ew_upcoming($all, $now, $limit);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (preg_match('#^https://([a-z0-9-]+\.)?entdeckerweine\.de$#i', $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if (count($all) === 0) {
    http_response_code(503);
    echo json_encode([
        'generated' => $now->format('c'),
        'source'    => 'unavailable',
        'count'     => 0,
        'events'    => [],
        'error'     => 'Termine sind derzeit nicht abrufbar.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode([
    'generated' => $now->format('c'),
    'source'    => $fromCache ? 'cache' : 'live',
    'count'     => count($upcoming),
    'events'    => $upcoming,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
