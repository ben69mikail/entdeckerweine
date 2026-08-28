/*!
 * EntdeckerWeine — Kommende Events
 *
 * Holt die Termine bei jedem Seitenaufruf von events.php (gleiche Domain, liest live
 * aus dem Shop), filtert gegen das aktuelle Datum und zeigt die drei nächsten an.
 * Vergangene Termine können dadurch nicht mehr stehenbleiben.
 */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) { module.exports = api; }
  if (root) { root.EWUpcomingEvents = api; }
  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', api.init);
    } else {
      api.init();
    }
  }
})(typeof self !== 'undefined' ? self : null, function () {
  'use strict';

  var MONTHS = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
  var EVENTS_PAGE = 'veranstaltungen.html';
  var DEFAULT_ENDPOINT = 'events.php';
  var DEFAULT_LIMIT = 3;

  var TAG_RULES = [
    [/whisk(e)?y|obe light/i, 'Whisky-Tasting'],
    [/käse|kaese|cheese/i, 'Wein & Käse'],
    [/lesung|literatur|autor|buch/i, 'Lesung'],
    [/poetry|slam|kabarett|comedy/i, 'Bühne'],
    [/konzert|concert|live|quartett|quintett|trio|band|jazz|gesang|musik/i, 'Konzert'],
    [/gin|rum|schnaps|destill|spirituose/i, 'Tasting'],
    [/tasting|verkostung|weinprobe|probe|abend|wein|champagner|bordeaux|italia/i, 'Weinprobe']
  ];

  /* ---------- Datum ---------- */

  // Liest Datumsteile direkt aus dem ISO-String, damit die Anzeige unabhängig
  // von der Zeitzone des Besuchers immer das deutsche Datum zeigt.
  function isoParts(start) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(start || ''));
    return m ? { year: m[1], month: parseInt(m[2], 10), day: m[3] } : null;
  }

  function formatDay(start) {
    var p = isoParts(start);
    return p ? p.day : '';
  }

  function formatMonth(start) {
    var p = isoParts(start);
    return p ? (MONTHS[p.month - 1] || '') : '';
  }

  function toDate(start) {
    if (typeof start !== 'string' || start === '') { return null; }
    var d = new Date(start);
    return isNaN(d.getTime()) ? null : d;
  }

  /**
   * Nur zukünftige Termine, chronologisch, maximal `limit` Stück.
   * options.skipSoldOut = true blendet ausverkaufte Termine aus.
   */
  function selectUpcoming(events, now, limit, options) {
    if (!Array.isArray(events)) { return []; }
    var reference = now instanceof Date && !isNaN(now.getTime()) ? now : new Date();
    var max = typeof limit === 'number' && limit >= 0 ? limit : DEFAULT_LIMIT;
    var skipSoldOut = !!(options && options.skipSoldOut);

    return events
      .filter(function (event) { return !(skipSoldOut && event && event.soldOut); })
      .map(function (event) { return { event: event, date: toDate(event && event.start) }; })
      .filter(function (row) { return row.date !== null && row.date.getTime() >= reference.getTime(); })
      .sort(function (a, b) { return a.date - b.date; })
      .slice(0, max)
      .map(function (row) { return row.event; });
  }

  /* ---------- Textbausteine ---------- */

  function formatPrice(price) {
    if (typeof price !== 'number' || isNaN(price)) { return null; }
    var text = price % 1 === 0 ? String(price) : price.toFixed(2).replace('.', ',');
    return text + ' €';
  }

  function formatDetail(event) {
    var parts = [];
    if (event && event.time) { parts.push('Ab ' + event.time + ' Uhr'); }
    if (event && event.location) { parts.push(event.location); }
    var price = formatPrice(event && event.price);
    if (price) { parts.push(price); }
    return parts.join(' · ');
  }

  function deriveTag(title) {
    var text = String(title || '');
    for (var i = 0; i < TAG_RULES.length; i++) {
      if (TAG_RULES[i][0].test(text)) { return TAG_RULES[i][1]; }
    }
    return 'Veranstaltung';
  }

  /* ---------- Rendering ---------- */

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Nur http/https zulassen — alles andere fällt auf die eigene Events-Seite zurück.
  function safeUrl(url) {
    return /^https?:\/\/[^\s"'<>]+$/i.test(String(url || '')) ? String(url) : EVENTS_PAGE;
  }

  function isExternal(url) {
    return /^https?:\/\//i.test(url);
  }

  function renderCard(event, index) {
    var url = safeUrl(event && event.url);
    var external = isExternal(url);
    var soldOut = !!(event && event.soldOut);
    var label = soldOut ? 'Details' : 'Tickets';
    var delay = (Math.max(0, index || 0) * 0.08).toFixed(2);

    return '' +
      '<article class="event-item ew-event-item" style="animation-delay:' + delay + 's;">' +
        '<div class="event-date">' +
          '<span class="event-date-day">' + escapeHtml(formatDay(event && event.start)) + '</span>' +
          '<span class="event-date-month">' + escapeHtml(formatMonth(event && event.start)) + '</span>' +
        '</div>' +
        '<div>' +
          '<div class="event-info-tag">' + escapeHtml(deriveTag(event && event.title)) + '</div>' +
          '<div class="event-info-title">' + escapeHtml(event && event.title) + '</div>' +
          '<div class="event-info-detail">' + escapeHtml(formatDetail(event || {})) + '</div>' +
        '</div>' +
        '<a class="btn btn-crimson ew-event-cta" href="' + escapeHtml(url) + '"' +
          (external ? ' target="_blank" rel="noopener"' : '') +
          ' aria-label="' + escapeHtml(label + ': ' + (event && event.title ? event.title : 'Veranstaltung')) + '"' +
          ' style="font-size:0.75rem; padding:0.6rem 1.1rem; white-space:nowrap;">' + label + '</a>' +
      '</article>';
  }

  function renderEmpty() {
    return '' +
      '<div class="event-item ew-event-item ew-event-empty">' +
        '<div>' +
          '<div class="event-info-tag">Termine</div>' +
          '<div class="event-info-title">Die nächsten Termine stehen im Veranstaltungskalender</div>' +
          '<div class="event-info-detail">Weinproben, Tastings, Lesungen und Konzerte in Gladbeck — alle aktuellen Termine auf einen Blick.</div>' +
        '</div>' +
        '<a class="btn btn-crimson ew-event-cta" href="' + EVENTS_PAGE + '" style="font-size:0.75rem; padding:0.6rem 1.1rem; white-space:nowrap;">Zum Kalender</a>' +
      '</div>';
  }

  /* ---------- Strukturierte Daten (Google / KI-Suche) ---------- */

  function buildJsonLd(events) {
    if (!Array.isArray(events) || events.length === 0) { return ''; }

    var graph = events.map(function (event) {
      var node = {
        '@type': 'Event',
        name: String(event.title || ''),
        startDate: String(event.start || ''),
        eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
        eventStatus: 'https://schema.org/EventScheduled',
        url: safeUrl(event.url),
        location: {
          '@type': 'Place',
          name: 'Entdeckerweine',
          address: {
            '@type': 'PostalAddress',
            streetAddress: 'Marktstraße 6',
            postalCode: '45964',
            addressLocality: 'Gladbeck',
            addressRegion: 'Nordrhein-Westfalen',
            addressCountry: 'DE'
          }
        },
        organizer: {
          '@type': 'Organization',
          name: 'Entdeckerweine',
          url: 'https://www.entdeckerweine.de/'
        }
      };
      if (event.image) { node.image = String(event.image); }
      if (typeof event.price === 'number' && !isNaN(event.price)) {
        node.offers = {
          '@type': 'Offer',
          price: String(event.price % 1 === 0 ? event.price : event.price.toFixed(2)),
          priceCurrency: 'EUR',
          url: safeUrl(event.url),
          availability: event.soldOut ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock'
        };
      }
      return node;
    });

    return JSON.stringify({ '@context': 'https://schema.org', '@graph': graph });
  }

  /* ---------- Einbindung in die Seite ---------- */

  function injectJsonLd(events) {
    if (typeof document === 'undefined') { return; }
    var id = 'ew-events-jsonld';
    var existing = document.getElementById(id);
    var json = buildJsonLd(events);
    if (!json) {
      if (existing) { existing.parentNode.removeChild(existing); }
      return;
    }
    var script = existing || document.createElement('script');
    script.id = id;
    script.type = 'application/ld+json';
    script.textContent = json;
    if (!existing) { document.head.appendChild(script); }
  }

  function render(container, events) {
    container.innerHTML = events.length
      ? events.map(renderCard).join('')
      : renderEmpty();
    container.removeAttribute('aria-busy');
    injectJsonLd(events);
  }

  function init() {
    if (typeof document === 'undefined') { return; }
    var container = document.querySelector('[data-ew-events]');
    if (!container) { return; }

    var endpoint = container.getAttribute('data-ew-events-endpoint') || DEFAULT_ENDPOINT;
    var limit = parseInt(container.getAttribute('data-ew-events-limit'), 10);
    if (!(limit > 0)) { limit = DEFAULT_LIMIT; }
    // data-ew-events-skip-soldout="true" → nur noch buchbare Termine zeigen
    var skipSoldOut = String(container.getAttribute('data-ew-events-skip-soldout') || '').toLowerCase() === 'true';

    container.setAttribute('aria-busy', 'true');

    if (typeof fetch !== 'function') { render(container, []); return; }

    fetch(endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'limit=' + (limit + 6), {
      headers: { 'Accept': 'application/json' },
      credentials: 'omit'
    })
      .then(function (response) {
        if (!response.ok) { throw new Error('HTTP ' + response.status); }
        return response.json();
      })
      .then(function (data) {
        var events = Array.isArray(data) ? data : (data && Array.isArray(data.events) ? data.events : []);
        // Datumsfilter läuft hier im Browser — also bei jedem Seitenaufruf neu.
        render(container, selectUpcoming(events, new Date(), limit, { skipSoldOut: skipSoldOut }));
      })
      .catch(function () {
        render(container, []);
      });
  }

  return {
    selectUpcoming: selectUpcoming,
    formatDay: formatDay,
    formatMonth: formatMonth,
    formatDetail: formatDetail,
    formatPrice: formatPrice,
    deriveTag: deriveTag,
    renderCard: renderCard,
    renderEmpty: renderEmpty,
    buildJsonLd: buildJsonLd,
    init: init
  };
});
