(() => {
  'use strict';
  const toggle = document.querySelector('.menu-toggle');
  const nav = document.querySelector('.primary-navigation');
  const setMenu = (open) => {
    if (!toggle || !nav) return;
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('is-open', open);
  };
  toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
  nav?.addEventListener('click', (event) => { if (event.target.closest('a')) setMenu(false); });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') { setMenu(false); toggle.focus(); }
  });
  document.addEventListener('click', (event) => { if (nav?.classList.contains('is-open') && !event.target.closest('.site-header')) setMenu(false); });
  const desktop = window.matchMedia('(min-width: 781px)');
  desktop.addEventListener?.('change', (event) => { if (event.matches) setMenu(false); });

  // Enhance long-form posts without altering the content saved by the writer.
  const article = document.querySelector('[data-article-content]');
  const toc = document.querySelector('[data-article-toc]');
  if (article && toc) {
    const headings = [...article.querySelectorAll('h2,h3')].filter((heading) => heading.textContent.trim());
    if (headings.length >= 2) {
      const links = [];
      headings.forEach((heading) => {
        if (!heading.id) {
          const base = heading.textContent.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'noi-dung';
          let id = base;
          let count = 2;
          while (document.getElementById(id)) id = `${base}-${count++}`;
          heading.id = id;
        }
        const link = document.createElement('a');
        link.href = `#${encodeURIComponent(heading.id)}`;
        link.textContent = heading.textContent;
        if (heading.tagName === 'H3') link.className = 'is-subheading';
        toc.querySelector('nav').append(link);
        links.push(link);
      });
      toc.hidden = false;
      article.closest('.article-reading-layout').classList.add('has-toc');
      if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
          entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            links.forEach((link, index) => {
              if (headings[index] === entry.target) link.setAttribute('aria-current', 'location');
              else link.removeAttribute('aria-current');
            });
          });
        }, {rootMargin: '-15% 0px -65% 0px'});
        headings.forEach((heading) => observer.observe(heading));
      }
    }
  }

  document.querySelectorAll('[data-copy-article]').forEach((button) => button.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(button.dataset.copyArticle);
      button.textContent = 'Đã sao chép liên kết';
    } catch (_) {
      const status = button.parentElement.querySelector('[role="status"]');
      if (status) status.textContent = `Liên kết bài viết: ${button.dataset.copyArticle}`;
    }
  }));

  const filters = document.querySelectorAll('[data-filter]');
  const cards = document.querySelectorAll('[data-product-grid] [data-category]');
  filters.forEach((button) => button.addEventListener('click', () => {
    const filter = button.dataset.filter;
    filters.forEach((item) => { const active = item === button; item.classList.toggle('is-active', active); item.setAttribute('aria-pressed', String(active)); });
    let count = 0;
    cards.forEach((card) => { const show = filter === 'all' || card.dataset.category.split(' ').includes(filter); card.hidden = !show; if (show) count++; });
    const status = document.querySelector('[data-filter-status]');
    if (status) status.textContent = `Đang hiển thị ${count} sản phẩm.`;
  }));

  document.querySelectorAll('[data-copy]').forEach((button) => button.addEventListener('click', async () => {
    const status = button.parentElement.querySelector('.copy-status');
    try {
      if (!navigator.clipboard || !window.isSecureContext) throw new Error('clipboard');
      await navigator.clipboard.writeText(button.dataset.copy);
      if (status) { status.classList.remove('sr-only'); status.textContent = 'Đã sao chép'; }
    } catch (_) {
      if (status) { status.classList.remove('sr-only'); status.textContent = `Số tài khoản: ${button.dataset.copy}. Chạm giữ để sao chép.`; }
    }
  }));

  document.querySelectorAll('[data-quote-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      if (!window.fetch || !window.FormData || !window.hpvConfig) return;
      event.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('[type="submit"]');
      const label = button.querySelector('span');
      const status = form.querySelector('.form-status');
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      label.textContent = 'Đang gửi yêu cầu…';
      status.textContent = '';
      status.classList.remove('is-success');
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 25000);
      try {
        // Refresh the nonce so a cached contact page can still submit safely.
        const fresh = await fetch(window.hpvConfig.ajaxUrl, {method: 'POST', credentials: 'same-origin', body: new URLSearchParams({action: 'hpv_form_nonce'}), signal: controller.signal});
        const nonce = await fresh.json();
        if (!nonce.success || !nonce.data?.nonce) throw new Error('Không lấy được phiên gửi. Vui lòng tải lại trang và thử lại.');
        form.querySelector('[name="hpv_nonce"]').value = nonce.data.nonce;
        const data = new FormData(form);
        data.set('hpv_ajax', '1');
        const response = await fetch(form.getAttribute('action'), {method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal});
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || 'Chưa gửi được yêu cầu. Vui lòng thử lại hoặc gọi hotline.');
        status.textContent = result.data.message;
        status.classList.add('is-success');
        form.reset();
        const token = form.querySelector('[name="hpv_token"]');
        token.value = window.crypto?.randomUUID ? window.crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (part) => { const value = Math.floor(Math.random() * 16); return (part === 'x' ? value : (value & 3) | 8).toString(16); });
      } catch (error) {
        status.textContent = error.name === 'AbortError' ? 'Kết nối chưa phản hồi. Yêu cầu có thể đã được nhận; hãy thử lại sau một phút hoặc gọi hotline để kiểm tra.' : (error instanceof SyntaxError ? 'Máy chủ chưa trả về kết quả hợp lệ. Vui lòng thử lại hoặc gọi hotline.' : error.message || 'Không thể kết nối. Vui lòng thử lại hoặc gọi hotline.');
      } finally {
        clearTimeout(timeout);
        button.disabled = false;
        button.removeAttribute('aria-busy');
        label.textContent = 'Gửi yêu cầu báo giá';
        status.focus();
      }
    });
  });
})();
