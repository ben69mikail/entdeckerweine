# Go-Live-Checkliste — www.entdeckerweine.de

**Stand:** 2026-08-06 · Dateien liegen fertig auf dem Server (`/Entdeckerweine2026`, 73 Dateien, 13 MB)

---

## Wichtig vorab: die richtige Domain

Im Auftrag stand `entdeckerwein.de` — diese Domain **existiert nicht** (kein DNS-Eintrag, geprüft).
Die richtige Adresse ist **`entdeckerweine.de`** (mit „e" am Ende). Alles ist darauf ausgelegt:
Sitemap, Canonical-Tags und die Weiterleitungen zeigen auf **`https://www.entdeckerweine.de`**.

> Die alte WordPress-Seite leitet aktuell **www → ohne www** um. Neu ist es **umgekehrt**:
> ohne www → **www**. Das ist bewusst so, weil alle neuen Seiten `www` als offizielle Adresse
> ausweisen. Wichtig ist nur, dass es *eine* feste Variante gibt.

---

## Schritt 1 (heute): Testlauf — ✅ ABGESCHLOSSEN

`neu.entdeckerweine.de` → `/Entdeckerweine2026`, vollständig geprüft:

| Prüfung | Ergebnis |
|---|---|
| Automatische Prüfung (`verify-homepage.sh`) | **36 / 36 bestanden** |
| Alle 17 Seiten + 53 Bilder/Dateien einzeln aufgerufen | **0 Fehler** |
| Weiterleitungen alter WordPress-Adressen | alle 301, korrekt |
| Eigene 404-Seite | echter 404-Status, Seite im Design |
| Sicherheits-Header, gzip, Caching | aktiv |
| Ladezeit Startseite | **0,11 s** (2,6 MB inkl. Video) |
| Testadresse für Google gesperrt | `noindex` gesetzt ✅ |

Dabei behoben: **Dateirechte** waren zu eng (604 → 644; hätte auch die Hauptdomain betroffen)
und **`MultiViews`** von Apache fing die alten Adressen ab, bevor die Weiterleitungen griffen
(`/impressum/` lief auf 404 statt zur neuen Seite).

---

## Schritt 2 (morgen): Hauptdomain umstellen

**Wo:** IONOS-Panel → Domains & SSL → `entdeckerweine.de` → Ziel/Verzeichnis ändern

| | vorher | nachher |
|---|---|---|
| Zielordner | `/live` (altes WordPress) | **`/Entdeckerweine2026`** |

Gleiches für `www.entdeckerweine.de`, falls dort getrennt einstellbar.

**Der Ordner `/live` bleibt unangetastet liegen** — nichts wird gelöscht. Falls etwas schiefgeht,
genügt es, den Zielordner zurück auf `/live` zu stellen; die alte Seite ist sofort wieder da.

**SSL:** Nach der Umstellung prüfen, dass das Zertifikat für `entdeckerweine.de` **und**
`www.entdeckerweine.de` gilt. Falls IONOS es neu ausstellt, kann das bis zu 1 Stunde dauern.

---

## Schritt 3: Prüfen (dauert 1 Minute)

```bash
bash "GO-LIVE-Ordner/verify-homepage.sh" https://www.entdeckerweine.de
```

Erwartet: **26 PASS / 0 FAIL → GREEN**

Zusätzlich von Hand im Browser ansehen:
- Startseite auf Handy und Rechner
- Ein „Tickets"-Button → landet im Shop
- Kontaktformular absenden → kommt die Mail an?

---

## Schritt 4: Nach dem Go-Live

- [ ] **Google Search Console:** neue Property `https://www.entdeckerweine.de` anlegen,
      Sitemap `https://www.entdeckerweine.de/sitemap.xml` einreichen
- [ ] **Google Unternehmensprofil:** Website-Link prüfen (auf `www.` umstellen)
- [ ] Alte WordPress-Sitemaps in der Search Console entfernen
- [ ] Ein paar Tage die Search Console auf 404-Fehler beobachten

---

## Was bereits erledigt ist

| | |
|---|---|
| ✅ | Alle 73 Dateien auf dem Server, byte-genau geprüft |
| ✅ | **Logo, Favicon und 7 weitere Bilder** hingen noch an der alten WordPress-Installation → jetzt lokal eingebunden (93 Stellen), sonst wären sie nach der Umstellung verschwunden |
| ✅ | 4 fehlende Dateien ergänzt (`rose-section.jpg`, `MartinPortrait.JPG`, 2 Skript-Dateien) |
| ✅ | Bilder optimiert: Startseite **5,1 MB → 2,6 MB**, Bilder gesamt 27 MB → 12 MB (Originale gesichert) |
| ✅ | `.htaccess`: HTTPS + www erzwungen, **24 Weiterleitungen** von alten WordPress-Adressen, Caching, Sicherheits-Header |
| ✅ | Eigene 404-Seite im Seitendesign |
| ✅ | Verkaufs-Adressen (`/geschenkgutscheine/`, `/brot-bestellen/`, `/warenkorb/`, `/kasse/`, Produkte) leiten auf den Shop |
| ✅ | `datenschutz.html` in die Sitemap aufgenommen |
| ✅ | 53 interne Verlinkungen und Bilder einzeln aufgerufen — alle erreichbar |

## Hinweis für spätere Uploads

Beim Hochladen per `tar`/FTP setzen sich die Dateirechte auf `604` — der Webserver
verweigert dann den Zugriff (403). Nach jedem Upload einmal ausführen:

```bash
find . -type f -exec chmod 644 {} \; && find . -type d -exec chmod 755 {} \;
```

Nicht auf den Webserver gehören: `GO-LIVE-CHECKLISTE.md`, `verify-homepage.sh`, `.git/`
(liegen bewusst nur im Projektordner und auf GitHub).

## Offene Punkte (kein Hindernis für den Start)

- **Weinprobe-Seite:** Der Button „Jetzt Platz sichern" führt zum Kontaktformular, nicht in den
  Shop. Sinnvoll, wenn private Weinproben auf Anfrage laufen — falls die auch buchbar sein sollen,
  kurz Bescheid geben.
- **Hero-Video** auf der Startseite ist 1,9 MB. Könnte man noch verkleinern, ist aber vertretbar.
- Die Änderungen sind **noch nicht in Git eingecheckt** — auf Wunsch committe und pushe ich sie
  nach `github.com/ben69mikail/entdeckerweine`.
