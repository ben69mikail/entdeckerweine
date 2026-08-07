/**
 * Wine Showcase Widget – Entdecker Weine Gladbeck
 * Zeigt zufällig ausgewählte Weine pro Kategorie (max. 10)
 */
(function() {
  'use strict';

  function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  function pick(arr, n) {
    return shuffle(arr).slice(0, n);
  }

  function formatPrice(p) {
    return p.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function renderCards(wines, container) {
    container.innerHTML = '';
    wines.forEach((w, i) => {
      const card = document.createElement('div');
      card.className = 'wine-card';
      card.style.animationDelay = (i * 0.06) + 's';
      card.innerHTML = `
        <div class="wine-card-number">${String(i + 1).padStart(2, '0')}</div>
        <div class="wine-card-name">${w.name}</div>
        <div class="wine-card-price">${formatPrice(w.price)}<span>€</span></div>
      `;
      container.appendChild(card);
    });
  }

  function initShowcase(el) {
    const category = el.dataset.category;
    if (!category || !window.WEINE_DATA) return;

    const source = window.WEINE_DATA[category] || [];
    if (!source.length) { el.style.display = 'none'; return; }

    const maxN   = parseInt(el.dataset.max || '10', 10);
    const grid   = el.querySelector('.wine-grid');
    const btn    = el.querySelector('.wine-shuffle-btn');
    const count  = el.querySelector('.wine-showcase-count');

    if (count) count.textContent = source.length;

    function refresh() {
      renderCards(pick(source, maxN), grid);
    }

    refresh();
    if (btn) btn.addEventListener('click', refresh);
  }

  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.wine-showcase[data-category]').forEach(initShowcase);
  });

})();
