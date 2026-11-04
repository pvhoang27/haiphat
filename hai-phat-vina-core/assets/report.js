(() => {
  'use strict';
  document.querySelectorAll('.main-chart').forEach((chart) => {
    const tooltip = document.createElement('div');
    tooltip.className = 'chart-tooltip';
    tooltip.hidden = true;
    chart.appendChild(tooltip);
    chart.querySelectorAll('.chart-hit').forEach((hit) => {
      const title = hit.querySelector('title');
      if (!title) return;
      const text = title.textContent;
      title.remove();
      hit.addEventListener('pointermove', (event) => {
        tooltip.replaceChildren();
        text.split(' · ').forEach((line, index) => {
          const item = document.createElement(index === 0 ? 'strong' : 'span');
          item.textContent = line;
          tooltip.appendChild(item);
        });
        tooltip.hidden = false;
        const bounds = chart.getBoundingClientRect();
        const left = Math.min(Math.max(8, event.clientX - bounds.left + 12), bounds.width - tooltip.offsetWidth - 8);
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${Math.max(0, event.clientY - bounds.top - tooltip.offsetHeight - 12)}px`;
      });
      hit.addEventListener('pointerleave', () => { tooltip.hidden = true; });
    });
    chart.addEventListener('pointerleave', () => { tooltip.hidden = true; });
  });
})();
