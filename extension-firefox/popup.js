const popupApi = getExtensionApi();

async function loadPopup() {
  const analysisResponse = await popupApi.runtime.sendMessage({ type: 'GET_ACTIVE_ANALYSIS' });
  const settingsResponse = await popupApi.runtime.sendMessage({ type: 'GET_SETTINGS' });

  if (settingsResponse?.ok) {
    applyTheme(settingsResponse.settings.theme);
    document.getElementById('securityMode').value = settingsResponse.settings.securityMode;
    document.getElementById('themeMode').value = settingsResponse.settings.theme;
  }

  if (analysisResponse?.ok) {
    renderAnalysis(analysisResponse.analysis);
    return;
  }

  renderFailure(analysisResponse?.error || 'Bilinmeyen bir hata olustu.');
}

function renderAnalysis(analysis) {
  const appearance = verdictPresentation(analysis.verdict);
  document.getElementById('verdictLabel').textContent = appearance.text;
  document.getElementById('hostname').textContent = analysis.hostname || '-';
  document.getElementById('updatedAt').textContent = analysis.updatedAt || '-';
  document.getElementById('source').textContent = analysis.source || '-';

  const badge = document.getElementById('verdictBadge');
  badge.textContent = appearance.text;
  badge.className = `state state-${analysis.verdict}`;

  const reasons = document.getElementById('reasons');
  reasons.innerHTML = '';
  (analysis.reasons || []).forEach((reason) => {
    const item = document.createElement('li');
    item.textContent = reason;
    reasons.appendChild(item);
  });

  const referenceWrap = document.getElementById('referenceWrap');
  const referenceButton = document.getElementById('referenceButton');
  if (analysis.source === 'usom' && analysis.referenceUrl) {
    referenceButton.href = analysis.referenceUrl;
    referenceWrap.classList.remove('hidden');
  } else {
    referenceButton.removeAttribute('href');
    referenceWrap.classList.add('hidden');
  }
}

function renderFailure(message) {
  document.getElementById('verdictLabel').textContent = 'Denetim Basarisiz';
  document.getElementById('hostname').textContent = message;
  document.getElementById('updatedAt').textContent = '-';
  document.getElementById('source').textContent = '-';
  document.getElementById('verdictBadge').textContent = 'Hata';
  document.getElementById('verdictBadge').className = 'state state-suspicious';
  const reasons = document.getElementById('reasons');
  reasons.innerHTML = '<li>Bu sekme icin denetim sonucu alinamadi.</li>';
  document.getElementById('referenceWrap').classList.add('hidden');
}

function applyTheme(theme) {
  document.body.dataset.theme = theme === 'light' ? 'light' : 'dark';
}

document.getElementById('saveSettings').addEventListener('click', async () => {
  const settings = {
    securityMode: document.getElementById('securityMode').value,
    theme: document.getElementById('themeMode').value
  };
  const response = await popupApi.runtime.sendMessage({ type: 'SAVE_SETTINGS', settings });
  if (response?.ok) {
    applyTheme(response.settings.theme);
  }
});

loadPopup().catch((error) => {
  renderFailure(error.message);
});
