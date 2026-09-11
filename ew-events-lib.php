<?php
/**
 * EntdeckerWeine — Termin-Bibliothek
 *
 * Liest die Veranstaltungsliste des Shops (shop.entdeckerweine.de/veranstaltungen/)
 * und stellt sie als strukturierte Daten bereit. Wird von events.php genutzt.
 *
 * Bewusst ohne DOM-Extension und ohne Composer: läuft auf jedem Standard-PHP.
 */
declare(strict_types=1);

const EW_TIMEZONE      = 'Europe/Berlin';
const EW_EVENTS_URL    = 'https://shop.entdeckerweine.de/veranstaltungen/';
const EW_FALLBACK_LINK = 'https://shop.entdeckerweine.de/veranstaltungen/';

/** Deutsche Monatsnamen → Monatszahl. */
function ew_months(): array
{
    return [
        'januar' => 1, 'februar' => 2, 'märz' => 3, 'maerz' => 3, 'april' => 4,
        'mai' => 5, 'juni' => 6, 'juli' => 7, 'august' => 8, 'september' => 9,
        'oktober' => 10, 'november' => 11, 'dezember' => 12,
    ];
}

/** Tags entfernen, Entities auflösen, Whitespace normalisieren. */
function ew_text(string $html): string
{
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace(["\xC2\xA0", "\xE2\x80\x8B"], [' ', ''], $text); // NBSP, Zero-Width-Space
    return trim(preg_replace('/\s+/u', ' ', $text));
}

/**
 * "Samstag, 12. September 2026 · 19:00 Uhr" → "2026-09-12T19:00:00+02:00"
 * Gibt null zurück, wenn die Zeile kein gültiges Datum enthält.
 */
function ew_parse_german_datetime(string $line, string $timezone = EW_TIMEZONE): ?string
{
    $line = ew_text($line);
    if ($line === '') {
        return null;
    }
    if (!preg_match('/(\d{1,2})\.\s*([A-Za-zÄÖÜäöüß]+)\s+(\d{4})/u', $line, $m)) {
        return null;
    }
    $day   = (int) $m[1];
    $year  = (int) $m[3];
    $month = ew_months()[mb_strtolower($m[2], 'UTF-8')] ?? null;
    if ($month === null || !checkdate($month, $day, $year)) {
        return null;
    }

    $hour = 0;
    $min  = 0;
    if (preg_match('/(\d{1,2})[:.](\d{2})\s*Uhr/u', $line, $t)) {
        $hour = (int) $t[1];
        $min  = (int) $t[2];
    }
    if ($hour > 23 || $min > 59) {
        return null;
    }

    $date = (new DateTimeImmutable('now', new DateTimeZone($timezone)))
        ->setDate($year, $month, $day)
        ->setTime($hour, $min, 0);

    return $date->format('c');
}

/** Preisangabe aus "Tickets: 25.00€" → 25.0 (null, wenn keine erkennbar). */
function ew_parse_price(string $line): ?float
{
    if (!preg_match('/(\d+(?:[.,]\d{1,2})?)/u', ew_text($line), $m)) {
        return null;
    }
    return (float) str_replace(',', '.', $m[1]);
}

/**
 * Parst die Veranstaltungsliste des Shops.
 * Erwartet die Markup-Struktur des Event-Plugins (.ewel-zeile / .ewel-titel / .ewel-meta).
 *
 * @return array<int, array{title:string,start:?string,date:?string,time:?string,location:string,price:?float,priceText:?string,soldOut:bool,availability:?string,url:string,image:?string}>
 */
function ew_parse_events(string $html): array
{
    $chunks = preg_split('/<div[^>]*class="[^"]*\bewel-zeile\b[^"]*"[^>]*>/u', $html);
    if ($chunks === false || count($chunks) < 2) {
        return [];
    }
    array_shift($chunks); // alles vor der ersten Zeile

    $events = [];
    $seen   = [];

    foreach ($chunks as $chunk) {
        if (!preg_match('/<p[^>]*class="[^"]*\bewel-titel\b[^"]*"[^>]*>(.*?)<\/p>/us', $chunk, $t)) {
            continue;
        }
        $title = ew_text($t[1]);
        if ($title === '') {
            continue;
        }

        preg_match_all('/<p[^>]*class="[^"]*\bewel-meta\b[^"]*"[^>]*>(.*?)<\/p>/us', $chunk, $metas);

        $start = null;
        $time  = null;
        $location = '';
        $price = null;
        $priceText = null;

        foreach ($metas[1] as $meta) {
            $text = ew_text($meta);
            if ($text === '') {
                continue;
            }
            if ($start === null && ($parsed = ew_parse_german_datetime($text)) !== null) {
                $start = $parsed;
                if (preg_match('/(\d{1,2})[:.](\d{2})\s*Uhr/u', $text, $tm)) {
                    $time = sprintf('%02d:%02d', (int) $tm[1], (int) $tm[2]);
                }
                continue;
            }
            if (stripos($text, 'ticket') !== false || str_contains($text, '€')) {
                $price = ew_parse_price($text);
                $priceText = $text;
                continue;
            }
            if ($location === '') {
                $location = $text;
            }
        }

        if ($start === null) {
            continue; // ohne Datum kein Termin
        }

        $availability = null;
        if (preg_match('/<span[^>]*class="[^"]*\bewel-frei\b[^"]*"[^>]*>(.*?)<\/span>/us', $chunk, $f)) {
            $availability = ew_text($f[1]);
        }
        $soldOut = str_contains($chunk, 'ewel-frei--aus')
            || str_contains($chunk, 'ewel-btn--aus')
            || ($availability !== null && stripos($availability, 'ausverkauft') !== false);

        $url = EW_FALLBACK_LINK;
        if (preg_match('/<a[^>]*class="[^"]*\bewel-btn\b[^"]*"[^>]*href="([^"]+)"/us', $chunk, $a)
            || preg_match('/<a[^>]*href="([^"]+)"[^>]*class="[^"]*\bewel-btn\b[^"]*"/us', $chunk, $a)) {
            $href = html_entity_decode($a[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('#^https?://#i', $href)) {
                $url = $href;
            }
        }

        $image = null;
        if (preg_match('/<img[^>]*class="[^"]*\bewel-bild\b[^"]*"[^>]*src="([^"]+)"/us', $chunk, $i)
            || preg_match('/<img[^>]*src="([^"]+)"[^>]*class="[^"]*\bewel-bild\b[^"]*"/us', $chunk, $i)) {
            $candidate = html_entity_decode($i[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Nur echte http(s)-Adressen ohne Anfuehrungs-/Steuerzeichen uebernehmen —
            // gleiche Pruefung wie bei $url, damit kein data:/javascript: durchrutscht.
            if (preg_match('#^https?://[^\s"\'<>]+$#i', $candidate)) {
                $image = $candidate;
            }
        }

        $key = $title . '|' . $start;
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;

        $events[] = [
            'title'        => $title,
            'start'        => $start,
            'date'         => substr($start, 0, 10),
            'time'         => $time,
            'location'     => $location,
            'price'        => $price,
            'priceText'    => $priceText,
            'soldOut'      => $soldOut,
            'availability' => $availability,
            'url'          => $url,
            'image'        => $image,
        ];
    }

    return $events;
}

/**
 * Filtert auf zukünftige Termine, sortiert chronologisch, begrenzt auf $limit.
 */
function ew_upcoming(array $events, DateTimeImmutable $now, int $limit): array
{
    $future = [];
    foreach ($events as $event) {
        $start = $event['start'] ?? null;
        if (!is_string($start) || $start === '') {
            continue;
        }
        try {
            $date = new DateTimeImmutable($start);
        } catch (Exception $e) {
            continue;
        }
        if ($date < $now) {
            continue;
        }
        $future[] = ['sort' => $date->getTimestamp(), 'event' => $event];
    }

    usort($future, fn($a, $b) => $a['sort'] <=> $b['sort']);
    if ($limit < 0) {
        $limit = 0;
    }

    return array_map(fn($row) => $row['event'], array_slice($future, 0, $limit));
}
