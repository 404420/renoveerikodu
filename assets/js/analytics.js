(function () {
  'use strict';
  var config = window.RK_ANALYTICS_CONFIG || {};
  var id = config.measurementId || '';
  if (!/^G-[A-Z0-9]+$/.test(id) || !(config.allowedHosts || []).includes(location.hostname)) return;
  var key = 'rk-analytics-consent-v1', consent = '', started = false;
  try { consent = localStorage.getItem(key) || ''; } catch (_) {}
  function gtag() { window.dataLayer.push(arguments); }
  function start() {
    window['ga-disable-' + id] = false;
    if (started) { gtag('consent', 'update', { analytics_storage: 'granted' }); return; }
    started = true;
    window.dataLayer = window.dataLayer || [];
    window.gtag = gtag;
    gtag('consent', 'default', { analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
    gtag('consent', 'update', { analytics_storage: 'granted' });
    gtag('js', new Date());
    var canonical = document.querySelector('link[rel="canonical"]');
    var page = canonical ? canonical.href : location.origin + location.pathname;
    gtag('config', id, { send_page_view: false, allow_google_signals: false, allow_ad_personalization_signals: false, page_location: page, page_referrer: '' });
    gtag('event', 'page_view', { page_location: page, page_title: document.title, page_referrer: '' });
    var script = document.createElement('script');
    script.async = true;
    script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    document.head.appendChild(script);
  }
  function track(name, params) {
    if (consent !== 'granted' || !started) return;
    gtag('event', name, Object.assign({ transport_type: 'beacon' }, params));
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest && event.target.closest('a[href]');
    if (!link) return;
    var href = link.getAttribute('href');
    if (/^tel:/i.test(href)) track('phone_click', { contact_method: 'phone' });
    if (/^mailto:/i.test(href)) track('email_click', { contact_method: 'email' });
  });
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (input.type !== 'file' || !input.closest('#contactForm') || !input.files.length) return;
    track('contact_file_added', { form_id: 'contactForm', file_count: input.files.length });
  });
  // Fired only after /api/contact.php confirms success, never on a button click.
  document.addEventListener('rk:contact-success', function () {
    track('generate_lead', { form_id: 'contactForm', method: 'contact_form' });
  });
  function setupConsent() {
    var panel = document.createElement('section');
    panel.className = 'analytics-consent';
    panel.setAttribute('aria-label', 'Statistika valik');
    panel.setAttribute('data-nosnippet', '');
    panel.innerHTML = '<p>Kas lubad külastusstatistikat? See aitab meil hinnata, millised lehed ja kontaktivõimalused on kasulikud. <a href="privaatsuspoliitika.html#veebistatistika">Loe lähemalt</a>.</p><div><button type="button" data-choice="granted">Luban</button><button type="button" data-choice="denied">Ei luba</button></div>';
    panel.hidden = !!consent;
    document.body.appendChild(panel);
    var settings = document.createElement('button');
    settings.type = 'button'; settings.className = 'analytics-settings'; settings.textContent = 'Statistika seaded';
    settings.addEventListener('click', function () { panel.hidden = false; panel.querySelector('button').focus(); });
    (document.querySelector('#footer') || document.body).appendChild(settings);
    panel.addEventListener('click', function (event) {
      var button = event.target.closest('[data-choice]');
      if (!button) return;
      consent = button.getAttribute('data-choice');
      try { localStorage.setItem(key, consent); } catch (_) {}
      panel.hidden = true;
      if (consent === 'granted') start();
      else {
        window['ga-disable-' + id] = true;
        if (started) gtag('consent', 'update', { analytics_storage: 'denied' });
        document.cookie.split(';').forEach(function (cookie) {
          var name = cookie.trim().split('=')[0];
          if (!/^_ga(?:_|$)/.test(name)) return;
          ['', '; domain=' + location.hostname, '; domain=.renoveerikodu.ee'].forEach(function (domain) {
            document.cookie = name + '=; Max-Age=0; path=/' + domain + '; SameSite=Lax';
          });
        });
      }
    });
    if (consent === 'granted') start();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', setupConsent, { once: true });
  else setupConsent();
})();
