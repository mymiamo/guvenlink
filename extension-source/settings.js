const settingsApi = getExtensionApi();

function sendMsg(message) {
  return sendRuntimeMessage(message).catch(() => null);
}

function formatRelativeTime(isoString) {
  if (!isoString) return '-';
  const diff = Date.now() - new Date(isoString).getTime();
  if (diff < 60000) return 'Az önce';
  if (diff < 3600000) return `${Math.floor(diff / 60000)} dakika önce`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} saat önce`;
  return `${Math.floor(diff / 86400000)} gün önce`;
}

function renderWhitelist(list) {
  const ul = document.getElementById('whitelistItems');
  ul.innerHTML = '';
  if (!list.length) {
    ul.innerHTML = '<li class="whitelist-empty">Henüz eklenen alan yok.</li>';
    return;
  }

  list.forEach((hostname) => {
    const li = document.createElement('li');
    li.className = 'whitelist-item';
    const span = document.createElement('span');
    span.textContent = hostname;
    const btn = document.createElement('button');
    btn.textContent = '✕';
    btn.className = 'whitelist-remove';
    btn.addEventListener('click', async () => {
      await sendMsg({ type: 'REMOVE_WHITELIST', hostname });
      const resp = await sendMsg({ type: 'GET_WHITELIST' });
      if (resp?.ok) renderWhitelist(resp.whitelist);
    });
    li.append(span, btn);
    ul.appendChild(li);
  });
}

document.getElementById('saveSettings').addEventListener('click', async () => {
  const settings = {
    securityMode: document.getElementById('securityMode').value,
    offlineBehavior: document.getElementById('offlineBehavior').value,
    telemetryEnabled: document.getElementById('telemetryEnabled').checked,
    theme: document.getElementById('themeMode').value
  };
  const resp = await sendMsg({ type: 'SAVE_SETTINGS', settings });
  document.getElementById('saveStatus').textContent = resp?.ok ? 'Ayarlar kaydedildi.' : 'Ayarlar kaydedilemedi.';
  document.body.dataset.theme = settings.theme === 'light' ? 'light' : 'dark';
});

document.getElementById('syncNow').addEventListener('click', async () => {
  const resp = await sendMsg({ type: 'SYNC_NOW' });
  document.getElementById('syncStatus').textContent = resp?.ok ? 'Senkronizasyon tamamlandı.' : 'Senkronizasyon başarısız.';
  if (resp?.ok) document.getElementById('syncedAt').textContent = formatRelativeTime(resp.syncedAt);
});

document.getElementById('whitelistAdd').addEventListener('click', async () => {
  const input = document.getElementById('whitelistInput');
  const hostname = input.value.trim().replace(/^https?:\/\//i, '').replace(/\/.*$/, '');
  if (!hostname) return;
  await sendMsg({ type: 'ADD_WHITELIST', hostname });
  input.value = '';
  const resp = await sendMsg({ type: 'GET_WHITELIST' });
  if (resp?.ok) renderWhitelist(resp.whitelist);
});

async function load() {
  const [settingsResp, metaResp, whitelistResp] = await Promise.all([
    sendMsg({ type: 'GET_SETTINGS' }),
    sendMsg({ type: 'GET_SYNC_META' }),
    sendMsg({ type: 'GET_WHITELIST' })
  ]);

  if (settingsResp?.ok) {
    const settings = settingsResp.settings;
    document.getElementById('securityMode').value = settings.securityMode;
    document.getElementById('offlineBehavior').value = settings.offlineBehavior;
    document.getElementById('telemetryEnabled').checked = !!settings.telemetryEnabled;
    document.getElementById('themeMode').value = settings.theme;
    document.body.dataset.theme = settings.theme === 'light' ? 'light' : 'dark';
  }

  if (metaResp?.ok) {
    document.getElementById('syncedAt').textContent = formatRelativeTime(metaResp.syncedAt);
    document.getElementById('syncToken').textContent = metaResp.syncToken || '-';
  }

  if (whitelistResp?.ok) renderWhitelist(whitelistResp.whitelist);
}

load().catch(console.error);
