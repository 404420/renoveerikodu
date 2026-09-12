// Keep queued gtag events, but let visible content load before analytics.
(function () {
  function loadAnalytics() {
    var script = document.createElement('script');
    script.src = 'https://www.googletagmanager.com/gtag/js?id=G-FHY5FDJ5DH';
    script.async = true;
    script.fetchPriority = 'low';
    document.head.appendChild(script);
  }
  function schedule() {
    if ('requestIdleCallback' in window) window.requestIdleCallback(loadAnalytics, {timeout: 2000});
    else window.setTimeout(loadAnalytics, 0);
  }
  if (document.readyState === 'complete') schedule();
  else window.addEventListener('load', schedule, {once: true});
})();
