(() => {
  'use strict';
  if (!window.hpvTracking) return;
  let previous = {element: null, time: 0};
  document.addEventListener('click', (event) => {
    if (!event.isTrusted) return;
    const link = event.target.closest('a[href]');
    if (!link) return;
    let url;
    try { url = new URL(link.href, location.href); } catch (_) { return; }
    const type = url.protocol === 'tel:' ? 'phone' : /(^|\.)zalo\.(me|vn)$/i.test(url.hostname) ? 'zalo' : link.dataset.hpvTrack === 'link' || (url.protocol === 'https:' && url.origin !== location.origin) ? 'link' : null;
    if (!type || !crypto.randomUUID) return;
    const now = Date.now();
    if (previous.element === link && now - previous.time < 1500) return;
    previous = {element: link, time: now};
    const body = new URLSearchParams({action: 'hpv_track', event: type, key: crypto.randomUUID(), path: location.pathname});
    fetch(window.hpvTracking.endpoint, {method: 'POST', credentials: 'same-origin', body, keepalive: true}).catch(() => {});
  });
})();
