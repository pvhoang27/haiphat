(() => {
  'use strict';
  const normal = (text) => text.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase();
  const singleFile = window.parent !== window;
  const navigate = (target) => {
    if (singleFile) window.parent.postMessage({type: 'hpv-demo-navigate', target}, '*');
    else window.location.href = target;
  };
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href]');
    if (!link || !singleFile || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button) return;
    const target = link.getAttribute('href');
    if (target.startsWith('#')) {
      const element = document.getElementById(decodeURIComponent(target.slice(1)));
      if (element) { event.preventDefault(); element.scrollIntoView({behavior: 'smooth'}); }
      return;
    }
    const page = target.split(/[?#]/)[0];
    if ((window.hpvDemoPages || []).includes(page)) { event.preventDefault(); navigate(target); }
  });
  document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-demo-form]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
      if (!form.reportValidity()) return;
      const status = form.querySelector('.form-status');
      status.classList.remove('is-success');
      status.textContent = 'Đây là bản xem thử giao diện. Thông tin chưa được gửi hoặc lưu lại. Khi cài WordPress, form sẽ tiếp nhận yêu cầu báo giá thực tế.';
      status.focus();
    }
    if (form.matches('[data-demo-search]')) {
      event.preventDefault();
      event.stopImmediatePropagation();
      const data = new FormData(form);
      const query = new URLSearchParams({q: data.get('s') || ''});
      if (data.get('post_type')) query.set('type', data.get('post_type'));
      navigate('tim-kiem.html?' + query);
    }
  }, true);

  document.querySelectorAll('[data-copy-article]').forEach((button) => { button.dataset.copyArticle = window.hpvDemoDocumentUrl || location.href.split('#')[0]; });
  if (document.body.dataset.demoPage !== 'tim-kiem.html') return;
  const params = new URLSearchParams(window.hpvDemoQuery || location.search);
  const query = params.get('q') || '';
  const type = params.get('type') || '';
  const heading = document.querySelector('h1');
  heading.textContent = query ? `Kết quả cho “${query}”` : 'Tìm sản phẩm & bài viết';
  document.title = heading.textContent + ' — Hải Phát Vina';
  const field = document.querySelector('.search-form [name=s]');
  if (field) field.value = query;
  const grid = document.querySelector('.archive-section .article-grid');
  grid.replaceChildren();
  const words = normal(query).trim().split(/\s+/).filter(Boolean);
  const results = (window.hpvDemoSearch || []).filter((item) => (!type || item.postType === type) && words.every((word) => normal(item.title + ' ' + item.summary).includes(word)));
  const intro = document.querySelector('.page-intro>p');
  if (intro) intro.textContent = query ? `Tìm thấy ${results.length} nội dung phù hợp.` : 'Nhập từ khóa hoặc khám phá các nội dung bên dưới.';
  results.forEach((item) => {
    const card = document.createElement('article');
    card.className = 'article-card';
    const imageLink = document.createElement('a');
    imageLink.className = 'article-image';
    imageLink.href = item.url;
    imageLink.tabIndex = -1;
    imageLink.setAttribute('aria-hidden', 'true');
    const image = document.createElement('img');
    image.src = window.hpvDemoAssets?.[item.image] || item.image;
    image.alt = '';
    image.loading = 'lazy';
    imageLink.append(image);
    const info = document.createElement('div');
    info.className = 'article-info';
    const category = document.createElement('span');
    category.className = 'article-category';
    category.textContent = item.kind;
    const title = document.createElement('h3');
    const link = document.createElement('a');
    link.href = item.url;
    link.textContent = item.title;
    title.append(link);
    const description = document.createElement('p');
    description.textContent = item.summary;
    info.append(category, title, description);
    card.append(imageLink, info);
    grid.append(card);
  });
  if (!results.length) {
    const empty = document.createElement('div');
    empty.className = 'empty-state';
    const title = document.createElement('h2');
    title.textContent = 'Chưa tìm thấy nội dung phù hợp';
    const text = document.createElement('p');
    text.textContent = 'Thử từ khóa ngắn hơn như “mái xếp”, “bạt” hoặc “HDPE”.';
    empty.append(title, text);
    grid.append(empty);
  }
})();
