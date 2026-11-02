(() => {
  'use strict';
  if (!window.fetch || !window.FormData || !window.AbortController || !window.hpvContactConfig) return;

  document.querySelectorAll('[data-quote-form]').forEach((form) => {
    if (form.dataset.hpvEnhanced) return;
    form.dataset.hpvEnhanced = '1';
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity() || form.dataset.hpvSubmitting === 'true') return;
      const button = form.querySelector('[type="submit"]');
      const label = button?.querySelector('span');
      const status = form.querySelector('.form-status');
      if (!button || !label || !status) return;
      const originalLabel = label.textContent;
      form.dataset.hpvSubmitting = 'true';
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      label.textContent = 'Đang gửi yêu cầu…';
      status.textContent = '';
      status.classList.remove('is-success');
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 25000);
      try {
        const fresh = await fetch(window.hpvContactConfig.ajaxUrl, {
          method: 'POST', credentials: 'same-origin',
          body: new URLSearchParams({ action: 'hpv_form_nonce' }), signal: controller.signal
        });
        const nonce = await fresh.json();
        if (!fresh.ok || !nonce.success || !nonce.data?.nonce) {
          throw new Error('Không lấy được phiên gửi. Vui lòng tải lại trang và thử lại.');
        }
        form.querySelector('[name="hpv_nonce"]').value = nonce.data.nonce;
        const data = new FormData(form);
        data.set('hpv_ajax', '1');
        const response = await fetch(form.action, {
          method: 'POST', credentials: 'same-origin', body: data, signal: controller.signal
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
          throw new Error(result.data?.message || 'Chưa gửi được yêu cầu. Vui lòng thử lại hoặc gọi hotline.');
        }
        status.textContent = result.data.message;
        status.classList.add('is-success');
        form.reset();
        const token = form.querySelector('[name="hpv_token"]');
        if (token) {
          token.value = window.crypto?.randomUUID
            ? window.crypto.randomUUID()
            : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (part) => {
              const value = Math.floor(Math.random() * 16);
              return (part === 'x' ? value : (value & 3) | 8).toString(16);
            });
        }
      } catch (error) {
        status.textContent = error.name === 'AbortError'
          ? 'Kết nối chưa phản hồi. Yêu cầu có thể đã được nhận; hãy thử lại sau một phút hoặc gọi hotline để kiểm tra.'
          : error instanceof SyntaxError
            ? 'Máy chủ chưa trả về kết quả hợp lệ. Vui lòng thử lại hoặc gọi hotline.'
            : error.message || 'Không thể kết nối. Vui lòng thử lại hoặc gọi hotline.';
      } finally {
        clearTimeout(timeout);
        form.dataset.hpvSubmitting = 'false';
        button.disabled = false;
        button.removeAttribute('aria-busy');
        label.textContent = originalLabel;
        status.focus();
      }
    });
  });
})();
