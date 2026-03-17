(function () {
  const extensionApi = window.browser ?? window.chrome;

  document.addEventListener('click', async (event) => {
    if (event.defaultPrevented || event.button !== 0) {
      return;
    }

    const target = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!target) {
      return;
    }

    const href = target.href;
    if (!/^https?:/i.test(href)) {
      return;
    }

    try {
      const response = await extensionApi.runtime.sendMessage({ type: 'CHECK_LINK', url: href });
      const analysis = response?.analysis;
      if (!analysis || analysis.verdict !== 'malicious') {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      const warningUrl = extensionApi.runtime.getURL(`warning.html?target=${encodeURIComponent(href)}&verdict=${analysis.verdict}&source=${analysis.source}&reasons=${encodeURIComponent(JSON.stringify(analysis.reasons))}`);
      window.location.assign(warningUrl);
    } catch (error) {
      console.error('Guvenlik link kontrolu basarisiz:', error);
    }
  }, true);
})();

