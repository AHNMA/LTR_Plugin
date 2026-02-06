(function () {
  'use strict';

  var cfg = window.f1_logo_cfg || {};
  var LIGHT_LOGO = cfg.light || '';
  var DARK_LOGO  = cfg.dark || '';

  if (!LIGHT_LOGO || !DARK_LOGO) return;

  function isDark() {
    return document.documentElement && document.documentElement.getAttribute('data-dracula-scheme') === 'dark';
  }

  function findLogoImg() {
    // 1. Priorität: Unser Shortcode Target
    var target = document.querySelector('.f1-logo-target');
    if (target) return target;

    // 2. Fallback: Legacy Theme Selektor
    var scope = document.querySelector('.af-middle-header');
    if (!scope) return null;
    return scope.querySelector('a.custom-logo-link img.custom-logo, .custom-logo-link img.custom-logo');
  }

  function applySwap() {
    var img = findLogoImg();
    if (!img) return false;

    var targetUrl = isDark() ? DARK_LOGO : LIGHT_LOGO;

    // Nur ändern wenn wirklich nötig
    if (img.src !== targetUrl) {
      img.src = targetUrl;

      // optional: WP srcset/sizes entfernen, damit nichts zurücküberschreibt
      img.removeAttribute('srcset');
      img.removeAttribute('sizes');
    }
    return true;
  }

  // 1) Sofort versuchen (so früh wie möglich)
  if (applySwap()) return;

  // 2) Falls Logo/Container erst später gerendert wird: DOM beobachten
  var obs = new MutationObserver(function () {
    if (applySwap()) {
      // Logo gefunden & gesetzt -> weiter beobachten für Darkmode-Änderungen
    }
  });

  obs.observe(document.documentElement, { childList: true, subtree: true });

  // 3) Zusätzlich: Attributwechsel am <html> (Darkmode toggle) extrem schnell abfangen
  var htmlObs = new MutationObserver(function (muts) {
    for (var i = 0; i < muts.length; i++) {
      if (muts[i].type === 'attributes' && muts[i].attributeName === 'data-dracula-scheme') {
        applySwap();
        break;
      }
    }
  });
  htmlObs.observe(document.documentElement, { attributes: true, attributeFilter: ['data-dracula-scheme'] });

})();
