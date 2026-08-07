#!/bin/bash
# Go-Live-Verifikation fuer www.entdeckerweine.de
# Nutzung:  ./verify-homepage.sh [BASIS-URL]
# Beispiel: ./verify-homepage.sh https://www.entdeckerweine.de
#           ./verify-homepage.sh https://neu.entdeckerweine.de   (Test-Subdomain vor Umstellung)

set -u

BASE="${1:-https://www.entdeckerweine.de}"
PASS=0
FAIL=0

check() {
  if [ "$2" = "1" ]; then echo "  ✓ PASS: $1"; PASS=$((PASS+1))
  else echo "  ✗ FAIL: $1"; FAIL=$((FAIL+1)); fi
}

echo "==> Verifiziere $BASE"

# 1. Startseite laedt und ist die neue statische Seite (kein WordPress mehr)
CODE=$(curl -s -o /dev/null -w '%{http_code}' -m 20 -L "$BASE/")
check "Startseite HTTP 200 (ist: $CODE)" "$([ "$CODE" = "200" ] && echo 1 || echo 0)"

HTML=$(curl -s -m 25 -L "$BASE/")
check "Neue statische Seite (kein WordPress)" "$(echo "$HTML" | grep -q 'wp-content' && echo 0 || echo 1)"
check "Inhalt vorhanden (Entdeckerweine im Titel)" "$(echo "$HTML" | grep -qi '<title>[^<]*Entdeckerweine' && echo 1 || echo 0)"

# 2. Alle Unterseiten erreichbar
for page in wein-kaufen rotwein weisswein rose champagner spirituosen feinkost \
            weinbar speisekarte weinprobe veranstaltungen ueber-uns kontakt \
            impressum datenschutz; do
  PCODE=$(curl -s -o /dev/null -w '%{http_code}' -m 15 "$BASE/$page.html")
  check "Unterseite /$page.html (ist: $PCODE)" "$([ "$PCODE" = "200" ] && echo 1 || echo 0)"
done

# 3. Assets laden (Bilder/Fonts/CSS) - Stichproben inkl. der zuvor fehlenden Datei
for asset in "rose-section.jpg" "sitemap.xml" "robots.txt"; do
  ACODE=$(curl -s -o /dev/null -w '%{http_code}' -m 15 "$BASE/$asset")
  check "Asset /$asset (ist: $ACODE)" "$([ "$ACODE" = "200" ] && echo 1 || echo 0)"
done

# 4. Shop-Verlinkung: Kaufwege zeigen auf die Subdomain
check "Shop-Link im Menue vorhanden" "$(echo "$HTML" | grep -q 'shop.entdeckerweine.de' && echo 1 || echo 0)"
VER=$(curl -s -m 20 "$BASE/veranstaltungen.html")
check "Veranstaltungen verlinkt Ticket-Kauf im Shop" "$(echo "$VER" | grep -q 'shop.entdeckerweine.de' && echo 1 || echo 0)"
SHOPCODE=$(curl -s -o /dev/null -w '%{http_code}' -m 20 -L "https://shop.entdeckerweine.de/")
check "Shop erreichbar (ist: $SHOPCODE)" "$([ "$SHOPCODE" = "200" ] && echo 1 || echo 0)"

# 5. Weiterleitungen der alten WordPress-Adressen (SEO)
weiterleitung() { # $1=alter Pfad  $2=Ziel-Fragment
  sleep 0.3
  local ergebnis; ergebnis=$(curl -s -o /dev/null -w '%{http_code}|%{redirect_url}' -m 15 "$BASE/$1/")
  local code="${ergebnis%%|*}" ziel="${ergebnis#*|}"
  check "Weiterleitung /$1/ -> $2 (ist: $code)" \
        "$([ "$code" = "301" ] && echo "$ziel" | grep -q "$2" && echo 1 || echo 0)"
}
weiterleitung impressum          "impressum.html"
weiterleitung kontakt            "kontakt.html"
weiterleitung unser-team         "ueber-uns.html"
weiterleitung produkte           "wein-kaufen.html"
weiterleitung events-ditix       "veranstaltungen.html"
weiterleitung geschenkgutscheine "shop.entdeckerweine.de"
weiterleitung brot-bestellen     "shop.entdeckerweine.de"
weiterleitung warenkorb          "shop.entdeckerweine.de"

# 6. Eigene Fehlerseite mit korrektem Status
sleep 0.3
NFCODE=$(curl -s -o /dev/null -w '%{http_code}' -m 15 "$BASE/diese-seite-gibt-es-nicht")
check "Unbekannte Adresse liefert 404 (ist: $NFCODE)" "$([ "$NFCODE" = "404" ] && echo 1 || echo 0)"
check "Eigene 404-Seite wird angezeigt" \
      "$(curl -s -m 15 "$BASE/diese-seite-gibt-es-nicht" | grep -q 'nicht im Regal' && echo 1 || echo 0)"

# 7. SSL
SSL=$(curl -s -o /dev/null -w '%{ssl_verify_result}' -m 15 "$BASE/" 2>/dev/null || echo 99)
check "SSL-Zertifikat gueltig" "$([ "$SSL" = "0" ] && echo 1 || echo 0)"

# 6. Kein Verweis mehr auf die Netlify-Testadresse
check "Keine Netlify-URLs im Quelltext" "$(echo "$HTML" | grep -q 'netlify.app' && echo 0 || echo 1)"

echo ""
echo "==> Ergebnis: $PASS PASS / $FAIL FAIL"
if [ "$FAIL" = "0" ]; then echo "==> GREEN ✓"; else echo "==> RED ✗"; fi
exit "$FAIL"
