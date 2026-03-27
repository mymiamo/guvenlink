(function () {
  const extensionApi = getExtensionApi();
  let tooltipEl = null;
  let hoverTimeout = null;

  function removeTooltip() {
    if (tooltipEl) {
      tooltipEl.classList.remove('gl-tooltip-visible');
      setTimeout(() => {
        tooltipEl?.remove();
        tooltipEl = null;
      }, 180);
    }
    clearTimeout(hoverTimeout);
  }

  function showTooltip(anchor, verdict, text) {
    removeTooltip();
    const rect = anchor.getBoundingClientRect();
    const tip = document.createElement('div');
    tip.className = `gl-tooltip gl-tooltip-${verdict}`;
    const icon = verdict === 'malicious' ? '✖' : verdict === 'suspicious' ? '⚠' : verdict === 'unknown' ? '…' : verdict === 'loading' ? '↻' : '✔';
    tip.innerHTML = `<span class="gl-tooltip-icon">${icon}</span><span class="gl-tooltip-label">${text}</span>`;
    tip.style.left = `${rect.left + window.scrollX}px`;
    tip.style.top = `${rect.bottom + window.scrollY + 6}px`;
    document.body.appendChild(tip);
    tooltipEl = tip;
    requestAnimationFrame(() => requestAnimationFrame(() => tip.classList.add('gl-tooltip-visible')));
  }

  document.addEventListener('click', async (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!anchor || !/^https?:/i.test(anchor.href)) return;
    try {
      const response = await sendRuntimeMessage({ type: 'CHECK_LINK', url: anchor.href });
      const analysis = response?.analysis;
      if (!analysis || !['malicious', 'suspicious', 'unknown'].includes(analysis.verdict)) return;
      event.preventDefault();
      event.stopPropagation();
      window.location.assign(extensionApi.runtime.getURL(`warning.html?target=${encodeURIComponent(anchor.href)}&payload=${encodeURIComponent(JSON.stringify(analysis))}`));
    } catch {}
  }, true);

  document.addEventListener('mouseover', (event) => {
    const anchor = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!anchor || !/^https?:/i.test(anchor.href)) return;
    clearTimeout(hoverTimeout);
    hoverTimeout = setTimeout(async () => {
      showTooltip(anchor, 'loading', 'Kontrol ediliyor...');
      try {
        const response = await sendRuntimeMessage({ type: 'CHECK_LINK', url: anchor.href });
        const analysis = response?.analysis;
        if (!analysis) return removeTooltip();
        const labels = {
          malicious: 'Zararlı bağlantı',
          suspicious: 'Riskli bağlantı',
          unknown: 'Belirsiz sonuç',
          safe: 'Güvenli'
        };
        showTooltip(anchor, analysis.verdict, labels[analysis.verdict] || 'Güvenli');
      } catch {
        removeTooltip();
      }
    }, 300);
  }, true);

  document.addEventListener('mouseout', () => {
    clearTimeout(hoverTimeout);
    setTimeout(() => {
      if (!tooltipEl?.matches(':hover')) removeTooltip();
    }, 100);
  }, true);
})();
