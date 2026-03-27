if (typeof importScripts === 'function') {
  importScripts('shared.js', 'idb.js');
}

const api = getExtensionApi();
const actionApi = api.action ?? api.browserAction;
const ALLOW_STORAGE_KEY = 'guvenlinkAllowList';
const WHITELIST_KEY = 'guvenlinkWhitelist';
const STATS_KEY = 'guvenlinkStats';
const DIRECT_USOM_API_URL = 'https://www.usom.gov.tr/api/address/index';
const DIRECT_USOM_TIMEOUT_MS = 8000;
const analysisInFlight = new Map();
let cachedSettings = null;
let cachedWhitelist = null;
let cachedAllowList = null;

api.runtime.onInstalled.addListener(async () => {
  scheduleSync();
  setupContextMenu();
  await syncFeed(true);
});

api.runtime.onStartup.addListener(() => {
  scheduleSync();
  setupContextMenu();
});

api.storage?.onChanged?.addListener((changes, areaName) => {
  if (areaName !== 'local') return;
  if (changes.guvenlinkSettings) cachedSettings = changes.guvenlinkSettings.newValue || null;
  if (changes[WHITELIST_KEY]) cachedWhitelist = changes[WHITELIST_KEY].newValue || [];
  if (changes[ALLOW_STORAGE_KEY]) cachedAllowList = changes[ALLOW_STORAGE_KEY].newValue || {};
});

api.alarms.onAlarm.addListener(async (alarm) => {
  if (alarm.name === SYNC_ALARM_NAME) await syncFeed(false);
});

api.webNavigation?.onBeforeNavigate?.addListener((details) => {
  if (details.frameId !== 0 || !details.url || details.url.startsWith(api.runtime.getURL('warning.html'))) return;
  prewarmAnalysis(details.url);
});

api.tabs.onActivated.addListener(async ({ tabId }) => {
  const tab = await invokeApiMethod(api.tabs, 'get', tabId);
  if (tab?.url) await updateTabState(tab.id, tab.url);
});

api.tabs.onUpdated.addListener(async (tabId, changeInfo, tab) => {
  if (changeInfo.status === 'loading' && tab.url) await maybeRedirectMalicious(tabId, tab.url);
  if ((changeInfo.status === 'complete' || changeInfo.url) && tab.url) await updateTabState(tabId, tab.url);
});

api.runtime.onMessage.addListener((message, sender, sendResponse) => {
  handleMessage(message).then(sendResponse).catch((error) => sendResponse({ ok: false, error: error.message }));
  return true;
});

function scheduleSync() {
  api.alarms.create(SYNC_ALARM_NAME, { periodInMinutes: SYNC_INTERVAL_MINUTES });
}

function setupContextMenu() {
  if (!api.contextMenus?.create) return;
  try {
    api.contextMenus.removeAll(() => {
      api.contextMenus.create({ id: 'guvenlink-check', title: 'Güvenlink ile Kontrol Et', contexts: ['link'] });
    });
  } catch {}
}

api.contextMenus?.onClicked.addListener(async (info) => {
  if (info.menuItemId !== 'guvenlink-check' || !info.linkUrl) return;
  const analysis = await analyzeUrl(info.linkUrl);
  if (['malicious', 'suspicious', 'unknown'].includes(analysis.verdict)) {
    await invokeApiMethod(api.tabs, 'create', { url: buildWarningUrl(info.linkUrl, analysis) });
  } else {
    await notifySafeResult(analysis);
  }
});

async function handleMessage(message) {
  switch (message?.type) {
    case 'GET_ACTIVE_ANALYSIS': {
      const [tab] = await invokeApiMethod(api.tabs, 'query', { active: true, currentWindow: true });
      if (!tab?.url) return { ok: false, error: 'Aktif sekme bulunamadı.' };
      return { ok: true, analysis: await analyzeUrl(tab.url) };
    }
    case 'CHECK_LINK':
      return { ok: true, analysis: await analyzeUrl(message.url) };
    case 'CONTINUE_TO_SITE':
      await setAllowed(message.url);
      return { ok: true };
    case 'SYNC_NOW':
      await syncFeed(false, true);
      return { ok: true, syncedAt: await getMeta('syncedAt') };
    case 'GET_SYNC_META':
      return { ok: true, syncedAt: await getMeta('syncedAt'), syncToken: await getMeta('syncToken') };
    case 'GET_SETTINGS':
      return { ok: true, settings: await getSettings() };
    case 'SAVE_SETTINGS':
      await saveSettings(message.settings || {});
      return { ok: true, settings: await getSettings() };
    case 'GET_STATS':
      return { ok: true, stats: await getStats() };
    case 'GET_WHITELIST':
      return { ok: true, whitelist: await getWhitelist() };
    case 'ADD_WHITELIST':
      await addToWhitelist(message.hostname);
      return { ok: true };
    case 'REMOVE_WHITELIST':
      await removeFromWhitelist(message.hostname);
      return { ok: true };
    case 'REPORT_FALSE_POSITIVE':
      return { ok: await submitFalsePositive(message.target, message.note || '') };
    default:
      return { ok: false, error: 'Bilinmeyen istek.' };
  }
}

async function getSettings() {
  if (cachedSettings) {
    return { ...DEFAULT_SETTINGS, ...cachedSettings };
  }

  const stored = await invokeApiMethod(api.storage.local, 'get', ['guvenlinkSettings']);
  cachedSettings = stored.guvenlinkSettings || {};
  return { ...DEFAULT_SETTINGS, ...cachedSettings };
}

async function saveSettings(nextSettings) {
  const settings = { ...(await getSettings()), ...nextSettings };
  cachedSettings = settings;
  await invokeApiMethod(api.storage.local, 'set', { guvenlinkSettings: settings });
}

async function getWhitelist() {
  if (cachedWhitelist !== null) return cachedWhitelist;

  const stored = await invokeApiMethod(api.storage.local, 'get', [WHITELIST_KEY]);
  cachedWhitelist = stored[WHITELIST_KEY] || [];
  return cachedWhitelist;
}

async function addToWhitelist(hostname) {
  const list = [...(await getWhitelist())];
  if (!list.includes(hostname)) {
    list.push(hostname);
    cachedWhitelist = list;
    await invokeApiMethod(api.storage.local, 'set', { [WHITELIST_KEY]: list });
  }
}

async function removeFromWhitelist(hostname) {
  const list = (await getWhitelist()).filter((item) => item !== hostname);
  cachedWhitelist = list;
  await invokeApiMethod(api.storage.local, 'set', { [WHITELIST_KEY]: list });
}

async function isWhitelisted(hostname) {
  const list = await getWhitelist();
  return list.some((item) => hostname === item || hostname.endsWith(`.${item}`));
}

async function isAllowed(url) {
  const allowList = await getAllowList();
  const expiry = allowList[url];
  if (expiry && expiry > Date.now()) return true;
  if (expiry) {
    delete allowList[url];
    cachedAllowList = allowList;
    await invokeApiMethod(api.storage.local, 'set', { [ALLOW_STORAGE_KEY]: allowList });
  }
  return false;
}

async function setAllowed(url) {
  const allowList = await getAllowList();
  allowList[url] = Date.now() + ALLOW_TTL_MS;
  cachedAllowList = allowList;
  await invokeApiMethod(api.storage.local, 'set', { [ALLOW_STORAGE_KEY]: allowList });
}

async function getAllowList() {
  if (cachedAllowList) return cachedAllowList;

  const stored = await invokeApiMethod(api.storage.local, 'get', [ALLOW_STORAGE_KEY]);
  cachedAllowList = stored[ALLOW_STORAGE_KEY] || {};
  return cachedAllowList;
}

async function getStats() {
  const stored = await invokeApiMethod(api.storage.local, 'get', [STATS_KEY]);
  const stats = stored[STATS_KEY] || {};
  const today = new Date().toISOString().slice(0, 10);
  const todayData = stats[today] || {};
  return {
    blockedToday: todayData.blocked || 0,
    suspiciousToday: todayData.suspicious || 0,
    unknownToday: todayData.unknown || 0
  };
}

async function incrementStat(type) {
  const stored = await invokeApiMethod(api.storage.local, 'get', [STATS_KEY]);
  const stats = stored[STATS_KEY] || {};
  const today = new Date().toISOString().slice(0, 10);
  if (!stats[today]) stats[today] = { blocked: 0, suspicious: 0, unknown: 0 };
  stats[today][type] = (stats[today][type] || 0) + 1;
  await invokeApiMethod(api.storage.local, 'set', { [STATS_KEY]: stats });
}

async function syncFeed(forceSnapshot = false, manual = false) {
  const settings = await getSettings();
  const apiBaseUrl = settings.apiBaseUrl.replace(/\/$/, '');
  const syncToken = forceSnapshot ? null : await getMeta('syncToken');

  try {
    if (!syncToken) {
      let page = 1;
      const merged = [];
      while (true) {
        const response = await fetch(`${apiBaseUrl}/feed?page=${page}&perPage=5000`);
        if (!response.ok) throw new Error('snapshot_failed');
        const payload = await response.json();
        merged.push(...(payload.entries || []).map(mapFeedEntry));
        if (!payload.hasMore) {
          await clearThreatStores();
          await upsertThreatEntries(merged);
          await setMeta('feedVersion', payload.version || null);
          await setMeta('syncToken', payload.syncToken || null);
          break;
        }
        page += 1;
      }
    } else {
      const response = await fetch(`${apiBaseUrl}/feed?since=${encodeURIComponent(syncToken)}`);
      if (!response.ok) throw new Error('delta_failed');
      const payload = await response.json();
      await removeThreatEntries(payload.removed || []);
      await upsertThreatEntries((payload.updated || []).map(mapFeedEntry));
      await setMeta('feedVersion', payload.version || null);
      await setMeta('syncToken', payload.syncToken || syncToken);
    }
  } catch (error) {
    if (manual) throw error;
  }

  await setMeta('syncedAt', new Date().toISOString());
}

function mapFeedEntry(item) {
  return {
    value: item.value,
    status: item.status || 'black',
    type: item.type || 'domain',
    source: item.source || 'manual',
    reason: item.reason || null,
    updatedAt: item.updated_at || item.updatedAt || null
  };
}

async function analyzeUrl(url) {
  const settings = await getSettings();
  if (!/^https?:/i.test(url)) {
    return buildBrowserAnalysis(url);
  }

  const normalized = normalizeUrl(url);
  if (await isWhitelisted(normalized.hostname)) {
    return buildWhitelistAnalysis(normalized);
  }

  const localMatch = await lookupInBlocklist(normalized.hostname, normalized.normalizedUrl);
  if (localMatch) {
    const analysis = buildLocalMatchAnalysis(normalized, localMatch);
    await cacheAnalysis(normalized.normalizedUrl, analysis);
    return applySecurityMode(analysis, settings);
  }

  const shortenerAnalysis = buildRiskyShortenerAnalysis(url, normalized);
  if (shortenerAnalysis) {
    await cacheAnalysis(normalized.normalizedUrl, shortenerAnalysis);
    return applySecurityMode(shortenerAnalysis, settings);
  }

  const cached = await getCachedAnalysis(normalized.normalizedUrl);
  if (cached) return applySecurityMode(cached, settings);

  const pending = analysisInFlight.get(normalized.normalizedUrl);
  if (pending) {
    return applySecurityMode(await pending, settings);
  }

  const analysisPromise = resolveRemoteAnalysis(url, normalized, settings)
    .finally(() => analysisInFlight.delete(normalized.normalizedUrl));
  analysisInFlight.set(normalized.normalizedUrl, analysisPromise);

  return applySecurityMode(await analysisPromise, settings);
}

async function resolveRemoteAnalysis(url, normalized, settings) {
  const remote = await analyzeViaBackend(url, settings.apiBaseUrl);
  const directUsom = !remote || hasUsomDegraded(remote) ? await analyzeViaUsom(url, normalized) : null;

  if (remote) {
    const mergedRemote = directUsom ? mergeRemoteWithDirectUsom(remote, directUsom, normalized) : remote;
    await cacheAnalysis(normalized.normalizedUrl, mergedRemote);
    return mergedRemote;
  }

  const offlineAnalysis = buildOfflineAnalysis(url, normalized, settings, directUsom);
  await cacheAnalysis(normalized.normalizedUrl, offlineAnalysis);
  return offlineAnalysis;
}

function prewarmAnalysis(url) {
  if (!/^https?:/i.test(url)) return;
  analyzeUrl(url).catch(() => {});
}

function buildBrowserAnalysis(url) {
  return buildAnalysis('safe', { hostname: 'Tarayıcı içi sayfa', normalizedUrl: url }, {
    source: 'browser',
    reasons: ['Tarayıcı içi sayfalar harici denetime tabi değildir.'],
    score: 0,
    confidence: 'high',
    signals: [],
    checks: [],
    degraded: [],
    latencyMs: 0
  });
}

function buildWhitelistAnalysis(normalized) {
  return buildAnalysis('safe', normalized, {
    source: 'whitelist',
    reasons: ['Bu alan güvenilir listenizde bulunuyor.'],
    score: 0,
    confidence: 'high',
    signals: [{
      source: 'whitelist',
      label: 'Güvenilir liste',
      severity: 'low',
      code: 'WHITELIST',
      description: 'Alan adı kullanıcı güvenilir listesinde bulunuyor.'
    }],
    checks: [],
    degraded: [],
    latencyMs: 0
  });
}

function buildLocalMatchAnalysis(normalized, localMatch) {
  return buildAnalysis(localVerdict(localMatch.status), normalized, {
    type: localMatch.type,
    source: localMatch.source || 'local',
    reasons: [localMatch.reason || localReason(localMatch.status)],
    updatedAt: localMatch.updatedAt,
    score: localScore(localMatch.status),
    confidence: 'high',
    signals: [{
      source: localMatch.source || 'local',
      label: localLabel(localMatch.status),
      severity: localSeverity(localMatch.status),
      code: localMatch.status?.toUpperCase() || 'LOCAL',
      description: localMatch.reason || localReason(localMatch.status)
    }],
    checks: [],
    degraded: [],
    latencyMs: 0
  });
}

function buildRiskyShortenerAnalysis(url, normalized) {
  const signals = heuristicSignals(url, normalized).filter((signal) => signal.code === 'SHORTENER_RISKY');
  if (!signals.length) return null;

  return buildAnalysis('suspicious', normalized, {
    source: 'heuristic',
    reasons: signals.map((signal) => signal.description),
    score: Math.min(100, signals.reduce((total, signal) => total + (signal.weight || 0), 0)),
    confidence: 'high',
    signals,
    checks: [],
    degraded: [],
    latencyMs: 0
  });
}

async function analyzeViaBackend(url, apiBaseUrl) {
  try {
    const response = await fetch(`${apiBaseUrl.replace(/\/$/, '')}/check?url=${encodeURIComponent(url)}`);
    if (!response.ok) return null;
    const payload = await response.json();
    return payload?.verdict ? payload : null;
  } catch {
    return null;
  }
}

async function analyzeViaUsom(url, normalized = normalizeUrl(url)) {
  const candidates = buildUsomCandidates(normalized);

  for (const candidate of candidates) {
    const payload = await searchUsom(candidate.query);
    if (payload.status === 'degraded') {
      return payload;
    }

    for (const row of payload.models || []) {
      const rowType = String(row.type || 'domain');
      const rowUrl = String(row.url || '').trim().toLowerCase();
      if (rowType !== candidate.type || rowUrl !== candidate.exact) {
        continue;
      }

      const descCode = String(row.desc || '').trim().toUpperCase();
      const connectionCode = String(row.connectiontype || '').trim().toUpperCase();
      return {
        service: 'usom',
        status: 'match',
        matched: true,
        latencyMs: payload.latencyMs,
        source: 'usom',
        verdict: 'malicious',
        score: 100,
        confidence: 'high',
        reason: 'USOM kaydı nedeniyle zararlı olarak işaretlendi.',
        updatedAt: normalizeUsomDate(String(row.date || '')),
        referenceUrl: row.id ? `https://www.usom.gov.tr/adres/${row.id}` : null,
        usomDetails: {
          code: descCode,
          category: expandUsomCode(descCode),
          connectionCode,
          connectionType: expandUsomCode(connectionCode)
        },
        label: 'USOM kritik eşleşmesi'
      };
    }
  }

  return {
    service: 'usom',
    status: 'clean',
    matched: false,
    latencyMs: 0,
    label: 'USOM kaydında eşleşme bulunmadı.'
  };
}

function buildUsomCandidates(normalized) {
  const hostCandidates = [];
  const parts = normalized.hostname.split('.');
  while (parts.length > 2) {
    parts.shift();
    hostCandidates.push(parts.join('.'));
  }

  return [
    { query: normalized.normalizedUrl.toLowerCase(), type: 'url', exact: normalized.normalizedUrl.toLowerCase() },
    { query: normalized.hostname, type: 'domain', exact: normalized.hostname },
    ...hostCandidates.map((host) => ({ query: host, type: 'domain', exact: host }))
  ];
}

async function searchUsom(query) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), DIRECT_USOM_TIMEOUT_MS);
  const startedAt = Date.now();

  try {
    const response = await fetch(`${DIRECT_USOM_API_URL}?page=1&per-page=25&q=${encodeURIComponent(query)}`, {
      method: 'GET',
      headers: { Accept: 'application/json' },
      signal: controller.signal
    });

    if (!response.ok) {
      return {
        service: 'usom',
        status: 'degraded',
        matched: false,
        latencyMs: Date.now() - startedAt,
        models: [],
        label: 'USOM doğrudan sorgusu başarısız oldu.'
      };
    }

    const data = await response.json();
    return {
      service: 'usom',
      status: 'ok',
      matched: false,
      latencyMs: Date.now() - startedAt,
      models: data.models || []
    };
  } catch {
    return {
      service: 'usom',
      status: 'degraded',
      matched: false,
      latencyMs: Date.now() - startedAt,
      models: [],
      label: 'USOM doğrudan sorgusu zaman aşımına uğradı.'
    };
  } finally {
    clearTimeout(timeout);
  }
}

function mergeRemoteWithDirectUsom(remote, directUsom, normalized) {
  const checks = replaceUsomCheck(remote.checks || [], directUsom);
  const degraded = (remote.degraded || []).filter((item) => item !== 'usom');
  const latencyMs = Math.max(remote.latencyMs || 0, directUsom.latencyMs || 0);

  if (directUsom.status === 'match') {
    return buildAnalysis('malicious', normalized, {
      type: directUsom.type || 'url',
      source: 'usom',
      reasons: [directUsom.reason || 'USOM kaydı bulundu.'],
      updatedAt: directUsom.updatedAt || new Date().toISOString(),
      referenceUrl: directUsom.referenceUrl || null,
      usomDetails: directUsom.usomDetails || null,
      checks,
      degraded,
      latencyMs,
      score: 100,
      confidence: 'high',
      signals: [
        usomSignal(directUsom),
        ...(remote.signals || []).filter((signal) => signal.source !== 'usom')
      ]
    });
  }

  if (directUsom.status === 'clean') {
    const score = remote.score || 0;
    const verdict = degraded.length === 0
      ? score >= 80 ? 'malicious' : score >= 35 ? 'suspicious' : 'safe'
      : remote.verdict;
    const reasons = remote.signals?.length
      ? remote.signals.map((signal) => signal.description).filter(Boolean)
      : (remote.reasons || []);

    return {
      ...remote,
      verdict,
      source: verdict === 'safe' && score === 0 ? 'usom' : remote.source,
      reasons: reasons.length ? reasons : ['USOM kaydında eşleşme bulunmadı.'],
      checks,
      degraded,
      latencyMs,
      updatedAt: remote.updatedAt || new Date().toISOString()
    };
  }

  return {
    ...remote,
    checks,
    degraded: Array.from(new Set([...(remote.degraded || []), 'usom'])),
    latencyMs
  };
}

function buildOfflineAnalysis(url, normalized, settings, directUsom) {
  if (directUsom?.status === 'match') {
    return buildAnalysis('malicious', normalized, {
      type: directUsom.type || 'url',
      source: 'usom',
      reasons: [directUsom.reason || 'USOM kaydı bulundu.'],
      updatedAt: directUsom.updatedAt || new Date().toISOString(),
      referenceUrl: directUsom.referenceUrl || null,
      usomDetails: directUsom.usomDetails || null,
      checks: [formatUsomCheck(directUsom)],
      degraded: [],
      latencyMs: directUsom.latencyMs || 0,
      score: 100,
      confidence: 'high',
      signals: [usomSignal(directUsom)]
    });
  }

  const signals = heuristicSignals(url, normalized);
  const heuristicDescriptions = signals.map((signal) => signal.description);
  const score = Math.min(100, signals.reduce((total, signal) => total + (signal.weight || 0), 0));
  const onlyTrustedShortener = signals.length > 0 && signals.every((signal) => signal.code === 'SHORTENER_SAFE');
  const verdict = onlyTrustedShortener
    ? 'safe'
    : signals.some((signal) => signal.code === 'SHORTENER_RISKY')
      ? 'suspicious'
      : heuristicDescriptions.length
        ? 'suspicious'
        : 'safe';

  if (directUsom?.status === 'clean') {
    return buildAnalysis(verdict, normalized, {
      source: onlyTrustedShortener ? 'heuristic' : heuristicDescriptions.length ? 'guvenlink' : 'usom',
      reasons: heuristicDescriptions.length ? heuristicDescriptions : ['USOM kaydında eşleşme bulunmadı.'],
      score: verdict === 'safe' ? 0 : score,
      confidence: onlyTrustedShortener ? 'high' : 'medium',
      signals,
      checks: [formatUsomCheck(directUsom)],
      degraded: [],
      latencyMs: directUsom.latencyMs || 0
    });
  }

  return buildAnalysis(
    onlyTrustedShortener ? 'safe' : heuristicDescriptions.length ? verdict : settings.offlineBehavior === 'safe' ? 'safe' : 'unknown',
    normalized,
    {
      source: onlyTrustedShortener ? 'heuristic' : 'offline',
      reasons: heuristicDescriptions.length ? heuristicDescriptions : ['Sunucuya ve USOM servisine erişilemediği için sonuç belirsiz.'],
      score: onlyTrustedShortener ? 0 : heuristicDescriptions.length ? score : 0,
      confidence: onlyTrustedShortener ? 'high' : heuristicDescriptions.length ? 'medium' : 'low',
      signals,
      checks: directUsom ? [formatUsomCheck(directUsom)] : [],
      degraded: onlyTrustedShortener ? [] : directUsom?.status === 'degraded' ? ['usom'] : ['backend'],
      latencyMs: directUsom?.latencyMs || 0
    }
  );
}

function hasUsomDegraded(analysis) {
  return (analysis.checks || []).some((check) => check.service === 'usom' && check.status === 'degraded');
}

function replaceUsomCheck(checks, directUsom) {
  const next = checks.filter((check) => check.service !== 'usom');
  next.unshift(formatUsomCheck(directUsom));
  return next;
}

function formatUsomCheck(directUsom) {
  return {
    service: 'usom',
    status: directUsom.status || 'unknown',
    latencyMs: directUsom.latencyMs || 0,
    matched: !!directUsom.matched,
    label: directUsom.label || (directUsom.status === 'clean' ? 'USOM kaydında eşleşme bulunmadı.' : 'USOM kontrolü tamamlandı.')
  };
}

function usomSignal(directUsom) {
  return {
    source: 'usom',
    label: directUsom.label || 'USOM kritik eşleşmesi',
    severity: 'high',
    code: directUsom.usomDetails?.code || 'USOM',
    description: directUsom.reason || 'USOM kaydı bu adresi doğrudan zararlı olarak işaretliyor.'
  };
}

function expandUsomCode(code) {
  switch (code) {
    case 'BP':
      return 'Bankacılık - Oltalama nedeni ile engellendi';
    case 'PH':
      return 'Oltalama nedeni ile engellendi';
    case 'CA':
      return 'Siber Saldırı (Port Tarama, Kaba Kuvvet vb.)';
    case 'MC':
      return 'Zararlı Yazılım - Komuta Kontrol Merkezi';
    case 'MD':
      return 'Zararlı Yazılım Barındıran / Yayan Alan Adı';
    case 'MI':
      return 'Zararlı Yazılım Barındıran / Yayan IP';
    default:
      return code || '-';
  }
}

function normalizeUsomDate(value) {
  const parsed = Date.parse(value);
  return Number.isNaN(parsed) ? new Date().toISOString() : new Date(parsed).toISOString();
}

function buildAnalysis(verdict, normalized, match = {}) {
  return {
    targetUrl: match.targetUrl ?? normalized.originalUrl ?? `https://${normalized.normalizedUrl}`,
    normalizedUrl: normalized.normalizedUrl,
    hostname: normalized.hostname,
    verdict,
    matchedBy: match.type ?? null,
    source: match.source ?? 'local',
    reasons: match.reasons ?? [],
    updatedAt: match.updatedAt ?? new Date().toISOString(),
    referenceUrl: match.referenceUrl ?? null,
    usomDetails: match.usomDetails ?? null,
    checks: match.checks ?? [],
    degraded: match.degraded ?? [],
    latencyMs: match.latencyMs ?? null,
    score: match.score ?? 0,
    confidence: match.confidence ?? 'medium',
    signals: match.signals ?? []
  };
}

function applySecurityMode(analysis, settings) {
  if (analysis.verdict === 'unknown' && settings.offlineBehavior === 'safe') {
    return {
      ...analysis,
      verdict: 'safe',
      reasons: [...analysis.reasons, 'Çevrimdışı davranış güvenli olarak ayarlandı.']
    };
  }

  return analysis;
}

async function updateTabState(tabId, url) {
  try {
    const analysis = await analyzeUrl(url);
    const appearance = verdictPresentation(analysis.verdict);
    await invokeApiMethod(actionApi, 'setBadgeText', { tabId, text: appearance.badge });
    await invokeApiMethod(actionApi, 'setBadgeBackgroundColor', { tabId, color: appearance.color });
    await invokeApiMethod(actionApi, 'setTitle', { tabId, title: `Güvenlik: ${appearance.text}` });
    if (actionApi.setIcon) await invokeApiMethod(actionApi, 'setIcon', { tabId, path: verdictIconSet(analysis.verdict) });
  } catch {
    await invokeApiMethod(actionApi, 'setBadgeText', { tabId, text: '' });
  }
}

function verdictIconSet(verdict) {
  switch (verdict) {
    case 'malicious':
      return { 16: 'logo/16x16-danger.png', 32: 'logo/32x32-danger.png', 48: 'logo/48x48-danger.png', 128: 'logo/128x128-danger.png' };
    case 'suspicious':
    case 'unknown':
      return { 16: 'logo/16x16-waite.png', 32: 'logo/32x32-wait.png', 48: 'logo/48x48-wait.png', 128: 'logo/128x128-wait.png' };
    default:
      return { 16: 'logo/16x16-yes.png', 32: 'logo/32x32-yes.png', 48: 'logo/48x48-yes.png', 128: 'logo/128x128-yes.png' };
  }
}

async function maybeRedirectMalicious(tabId, url) {
  if (!/^https?:/i.test(url) || await isAllowed(url)) return;
  const settings = await getSettings();
  const analysis = await analyzeUrl(url);
  const shouldBlock = settings.securityMode === 'strict'
    ? ['malicious', 'suspicious', 'unknown'].includes(analysis.verdict)
    : settings.securityMode === 'balanced'
      ? analysis.verdict === 'malicious' || shouldWarnInBalanced(analysis)
      : false;

  if (!shouldBlock) return;

  await incrementStat(analysis.verdict === 'malicious' ? 'blocked' : analysis.verdict);
  if (!url.startsWith(api.runtime.getURL('warning.html'))) {
    await invokeApiMethod(api.tabs, 'update', tabId, { url: buildWarningUrl(url, analysis) });
  }
}

function buildWarningUrl(url, analysis) {
  return api.runtime.getURL(`warning.html?target=${encodeURIComponent(url)}&payload=${encodeURIComponent(JSON.stringify(analysis))}`);
}

function shouldWarnInBalanced(analysis) {
  if (analysis.verdict === 'unknown') return true;
  if (analysis.verdict !== 'suspicious') return false;
  if ((analysis.score || 0) >= 60) return true;
  return (analysis.signals || []).some((signal) => ['SHORTENER_RISKY', 'PUNYCODE', 'TLD'].includes(signal.code));
}

async function submitFalsePositive(target, note) {
  const settings = await getSettings();
  try {
    const response = await fetch(`${settings.apiBaseUrl.replace(/\/$/, '')}/report`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        url: target,
        kind: 'false_positive',
        note: note || 'Uyarı ekranından yanlış pozitif bildirimi gönderildi.'
      })
    });
    return response.ok;
  } catch {
    return false;
  }
}

async function notifySafeResult(analysis) {
  if (!api.notifications?.create) return;
  try {
    await invokeApiMethod(api.notifications, 'create', `guvenlink-safe-${Date.now()}`, {
      type: 'basic',
      iconUrl: api.runtime.getURL('logo/48x48-yes.png'),
      title: 'Güvenlink',
      message: `${analysis.hostname} güvenli görünüyor.`
    });
  } catch {}
}

function localVerdict(status) {
  if (status === 'white') return 'safe';
  if (status === 'suspicious') return 'suspicious';
  return 'malicious';
}

function localScore(status) {
  if (status === 'white') return 0;
  if (status === 'suspicious') return 72;
  return 95;
}

function localReason(status) {
  if (status === 'white') return 'Yerel beyaz liste eşleşmesi bulundu.';
  if (status === 'suspicious') return 'Yerel şüpheli kayıt eşleşmesi bulundu.';
  return 'Yerel kara liste eşleşmesi bulundu.';
}

function localLabel(status) {
  if (status === 'white') return 'Yerel beyaz liste';
  if (status === 'suspicious') return 'Yerel şüpheli kayıt';
  return 'Yerel kara liste';
}

function localSeverity(status) {
  if (status === 'white') return 'low';
  if (status === 'suspicious') return 'medium';
  return 'high';
}
