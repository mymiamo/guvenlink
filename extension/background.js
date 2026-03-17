importScripts('shared.js', 'idb.js');

const api = getExtensionApi();
const actionApi = api.action ?? api.browserAction;
const allowMap = new Map();
const DEFAULT_SETTINGS = {
  securityMode: 'balanced',
  theme: 'dark'
};

api.runtime.onInstalled.addListener(async () => {
  scheduleSync();
  await syncFeed();
});

api.runtime.onStartup.addListener(async () => {
  scheduleSync();
});

api.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === SYNC_ALARM_NAME) {
    await syncFeed();
  }
});

api.tabs.onActivated.addListener(async ({ tabId }) => {
  const tab = await api.tabs.get(tabId);
  if (tab?.url) {
    await updateTabState(tab.id, tab.url);
  }
});

api.tabs.onUpdated.addListener(async (tabId, changeInfo, tab) => {
  if (changeInfo.status === 'loading' && tab.url) {
    await maybeRedirectMalicious(tabId, tab.url);
  }

  if ((changeInfo.status === 'complete' || changeInfo.url) && tab.url) {
    await updateTabState(tabId, tab.url);
  }
});

api.runtime.onMessage.addListener((message, sender, sendResponse) => {
  handleMessage(message).then(sendResponse).catch((error) => {
    sendResponse({ ok: false, error: error.message });
  });
  return true;
});

async function handleMessage(message) {
  switch (message?.type) {
    case 'GET_ACTIVE_ANALYSIS': {
      const [tab] = await api.tabs.query({ active: true, currentWindow: true });
      if (!tab?.url) {
        return { ok: false, error: 'Aktif sekme bulunamadi.' };
      }
      return { ok: true, analysis: await analyzeUrl(tab.url) };
    }
    case 'CHECK_LINK':
      return { ok: true, analysis: await analyzeUrl(message.url) };
    case 'CONTINUE_TO_SITE':
      allowMap.set(message.url, Date.now() + ALLOW_TTL_MS);
      return { ok: true };
    case 'SYNC_NOW':
      await syncFeed();
      return { ok: true, syncedAt: await getMeta('syncedAt') };
    case 'GET_SYNC_META':
      return { ok: true, syncedAt: await getMeta('syncedAt') };
    case 'GET_SETTINGS':
      return { ok: true, settings: await getSettings() };
    case 'SAVE_SETTINGS':
      await saveSettings(message.settings || {});
      return { ok: true, settings: await getSettings() };
    default:
      return { ok: false, error: 'Bilinmeyen istek.' };
  }
}

async function getSettings() {
  const stored = await api.storage.local.get(['guvenlinkSettings']);
  return {
    ...DEFAULT_SETTINGS,
    ...(stored.guvenlinkSettings || {})
  };
}

async function saveSettings(nextSettings) {
  const merged = {
    ...(await getSettings()),
    ...nextSettings
  };
  await api.storage.local.set({ guvenlinkSettings: merged });
}

function scheduleSync() {
  api.alarms.create(SYNC_ALARM_NAME, { periodInMinutes: SYNC_INTERVAL_MINUTES });
}

async function syncFeed() {
  await setMeta('syncedAt', new Date().toISOString());
}

async function analyzeUrl(url) {
  const settings = await getSettings();

  if (!/^https?:/i.test(url)) {
    return {
      normalizedUrl: url,
      hostname: 'Tarayici ici sayfa',
      verdict: 'safe',
      matchedBy: null,
      source: 'browser',
      reasons: ['Bu sayfa tarayicinin kendi sayfasi oldugu icin harici denetim uygulanmadi.'],
      updatedAt: new Date().toISOString(),
      referenceUrl: null
    };
  }

  const remote = await analyzeViaBackend(url);
  if (remote) {
    return applySecurityMode(remote, settings.securityMode);
  }

  const normalized = normalizeUrl(url);
  const reasons = heuristicReasons(url, normalized);
  if (reasons.length) {
    return applySecurityMode(buildAnalysis('suspicious', normalized, null, reasons), settings.securityMode);
  }

  return applySecurityMode(buildAnalysis('safe', normalized, null, ['Kara liste eslesmesi bulunamadi.']), settings.securityMode);
}

async function analyzeViaBackend(url) {
  try {
    const response = await fetch(`${DEFAULT_API_BASE_URL}/check?url=${encodeURIComponent(url)}`);
    if (!response.ok) {
      return null;
    }
    const payload = await response.json();
    return payload?.verdict ? payload : null;
  } catch (error) {
    return null;
  }
}

function buildAnalysis(verdict, normalized, match, reasons) {
  return {
    normalizedUrl: normalized.normalizedUrl,
    hostname: normalized.hostname,
    verdict,
    matchedBy: match?.type ?? null,
    source: match?.source ?? 'local',
    reasons,
    updatedAt: match?.updatedAt ?? new Date().toISOString(),
    referenceUrl: match?.referenceUrl ?? null
  };
}

function applySecurityMode(analysis, securityMode) {
  if (securityMode === 'informational' && analysis.verdict === 'malicious') {
    return {
      ...analysis,
      reasons: [...analysis.reasons, 'Bilgilendirme modunda engelleme yerine yalnizca uyari gosterilir.']
    };
  }

  return analysis;
}

async function updateTabState(tabId, url) {
  try {
    const analysis = await analyzeUrl(url);
    const appearance = verdictPresentation(analysis.verdict);
    await actionApi.setBadgeText({ tabId, text: appearance.badge });
    await actionApi.setBadgeBackgroundColor({ tabId, color: appearance.color });
    await actionApi.setTitle({ tabId, title: `Guvenlik: ${appearance.text}` });
    await setActionIcon(tabId, appearance.color);
  } catch (error) {
    await actionApi.setBadgeText({ tabId, text: '' });
  }
}

async function setActionIcon(tabId, color) {
  const canvas = new OffscreenCanvas(16, 16);
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, 16, 16);
  ctx.fillStyle = '#ffffff';
  ctx.fillRect(0, 0, 16, 16);
  ctx.fillStyle = color;
  ctx.beginPath();
  ctx.arc(8, 8, 6, 0, Math.PI * 2);
  ctx.fill();
  ctx.strokeStyle = '#1f2937';
  ctx.lineWidth = 1.5;
  ctx.stroke();
  const imageData = ctx.getImageData(0, 0, 16, 16);
  await actionApi.setIcon({ tabId, imageData });
}

async function maybeRedirectMalicious(tabId, url) {
  if (!/^https?:/i.test(url)) {
    return;
  }

  const expiry = allowMap.get(url);
  if (expiry && expiry > Date.now()) {
    return;
  }

  const analysis = await analyzeUrl(url);
  const settings = await getSettings();
  const shouldBlock =
    (settings.securityMode === 'strict' && (analysis.verdict === 'malicious' || analysis.verdict === 'suspicious')) ||
    (settings.securityMode === 'balanced' && analysis.verdict === 'malicious');

  if (!shouldBlock) {
    return;
  }

  const warningUrl = api.runtime.getURL(`warning.html?target=${encodeURIComponent(url)}&verdict=${analysis.verdict}&source=${analysis.source}&reasons=${encodeURIComponent(JSON.stringify(analysis.reasons))}`);
  if (!url.startsWith(api.runtime.getURL('warning.html'))) {
    await api.tabs.update(tabId, { url: warningUrl });
  }
}
