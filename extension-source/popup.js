const popupApi = getExtensionApi();
let activeAnalysis = null;
let currentSettings = { ...DEFAULT_SETTINGS };

function sendMsg(message) {
  return new Promise((resolve) => {
    const timer = setTimeout(() => resolve(null), 5000);
    sendRuntimeMessage(message).then((result) => {
      clearTimeout(timer);
      resolve(result);
    }).catch(() => {
      clearTimeout(timer);
      resolve(null);
    });
  });
}

function formatRelativeTime(isoString) {
  if (!isoString) return '-';
  const diff = Date.now() - new Date(isoString).getTime();
  if (diff < 60000) return 'Az önce';
  if (diff < 3600000) return `${Math.floor(diff / 60000)} dakika önce`;
  if (diff < 86400000) return `${Math.floor(diff / 3600000)} saat önce`;
  return `${Math.floor(diff / 86400000)} gün önce`;
}

function updateVerdictIcon(verdict) {
  const img = document.getElementById('verdictIcon');
  const map = {
    malicious: 'logo/128x128-danger.png',
    suspicious: 'logo/128x128-wait.png',
    unknown: 'logo/128x128-wait.png',
    loading: 'logo/128x128-logo.png',
    safe: 'logo/128x128-yes.png'
  };
  img.src = map[verdict] || map.safe;
}

function confidenceLabel(value) {
  switch (value) {
    case 'high':
      return 'Yüksek';
    case 'medium':
      return 'Orta';
    case 'low':
      return 'Düşük';
    default:
      return '-';
  }
}

function sourceLabel(source) {
  switch (source) {
    case 'usom':
      return 'USOM';
    case 'manual':
      return 'Manuel kayıt';
    case 'whitelist':
      return 'Güvenilir liste';
    case 'offline':
      return 'Çevrimdışı karar';
    case 'browser':
      return 'Tarayıcı';
    default:
      return source || '-';
  }
}

function serviceLabel(service) {
  switch (service) {
    case 'usom':
      return 'USOM';
    case 'safe-browsing':
      return 'Google Safe Browsing';
    case 'virustotal':
      return 'VirusTotal';
    default:
      return service || 'Bilinmiyor';
  }
}

function statusLabel(status) {
  switch (status) {
    case 'match':
      return 'Eşleşme';
    case 'clean':
      return 'Temiz';
    case 'degraded':
      return 'Belirsiz';
    case 'disabled':
      return 'Kapalı';
    case 'ok':
      return 'Çalıştı';
    default:
      return status || '-';
  }
}

function severityLabel(severity) {
  switch (severity) {
    case 'high':
      return 'Yüksek';
    case 'medium':
      return 'Orta';
    case 'low':
      return 'Düşük';
    default:
      return 'Bilgi';
  }
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function buildStatsLine(analysis) {
  const serviceCount = (analysis.checks || []).length;
  const signalCount = (analysis.signals || []).length;
  return `${serviceCount} servis kontrolü · ${signalCount} tespit sinyali`;
}

function renderDegraded(analysis) {
  const card = document.getElementById('degradedCard');
  const text = document.getElementById('degradedText');
  if (!analysis.degraded?.length) {
    card.classList.add('hidden');
    text.textContent = '';
    return;
  }

  text.textContent = `Bazı harici servisler yanıt vermedi veya devre dışı kaldı: ${analysis.degraded.join(', ')}. Karar bu yüzden belirsizleşebilir.`;
  card.classList.remove('hidden');
}

function renderUsomDetails(details) {
  const wrap = document.getElementById('usomWrap');
  if (!details?.category && !details?.connectionType) {
    wrap.classList.add('hidden');
    return;
  }

  document.getElementById('usomCategory').textContent = details.category || '-';
  document.getElementById('usomType').textContent = details.connectionType || '-';
  wrap.classList.remove('hidden');
}

function renderSignals(analysis) {
  const list = document.getElementById('signals');
  list.innerHTML = '';
  const items = analysis.signals?.length
    ? analysis.signals.map((signal) => ({
      title: signal.label || signal.code || 'Tespit sinyali',
      meta: `${severityLabel(signal.severity)} · ${sourceLabel(signal.source)}`,
      description: signal.description || '-'
    }))
    : (analysis.reasons || []).map((reason) => ({
      title: 'Analiz notu',
      meta: sourceLabel(analysis.source),
      description: reason
    }));

  if (!items.length) {
    list.innerHTML = '<li class="rich-list-empty">Herhangi bir risk nedeni görünmüyor.</li>';
    return;
  }

  items.forEach((item) => {
    const li = document.createElement('li');
    li.className = 'rich-list-item';
    li.innerHTML = `<strong>${escapeHtml(item.title)}</strong><span class="small muted">${escapeHtml(item.meta)}</span><p>${escapeHtml(item.description)}</p>`;
    list.appendChild(li);
  });
}

function renderChecks(checks) {
  const list = document.getElementById('checks');
  list.innerHTML = '';
  if (!checks.length) {
    list.innerHTML = '<li class="rich-list-empty">Servis kontrolü bulunmuyor.</li>';
    return;
  }

  checks.forEach((check) => {
    const li = document.createElement('li');
    li.className = 'rich-list-item';
    const metaParts = [statusLabel(check.status)];
    if (check.matched) metaParts.push('Eşleşme var');
    if (check.latencyMs) metaParts.push(`${check.latencyMs} ms`);
    li.innerHTML = `<strong>${escapeHtml(serviceLabel(check.service))}</strong><span class="small muted">${escapeHtml(metaParts.join(' · '))}</span><p>${escapeHtml(check.label || 'Servis kontrolü tamamlandı.')}</p>`;
    list.appendChild(li);
  });
}

function renderAnalysis(analysis) {
  activeAnalysis = analysis;
  const appearance = verdictPresentation(analysis.verdict);
  document.getElementById('verdictLabel').textContent = appearance.text;
  document.getElementById('hostname').textContent = analysis.hostname || '-';
  document.getElementById('updatedAt').textContent = formatRelativeTime(analysis.updatedAt);
  document.getElementById('source').textContent = sourceLabel(analysis.source);
  document.getElementById('latency').textContent = analysis.latencyMs ? `${analysis.latencyMs} ms` : '-';
  document.getElementById('scoreValue').textContent = `${analysis.score ?? 0}/100`;
  document.getElementById('confidenceValue').textContent = confidenceLabel(analysis.confidence);
  document.getElementById('syncState').textContent = analysis.degraded?.length ? 'Kısmi doğrulama' : 'Tam';
  document.getElementById('statsLine').textContent = buildStatsLine(analysis);

  const badge = document.getElementById('verdictBadge');
  badge.textContent = appearance.text;
  badge.className = `state state-${analysis.verdict}`;
  updateVerdictIcon(analysis.verdict);

  renderDegraded(analysis);
  renderUsomDetails(analysis.usomDetails);
  renderSignals(analysis);
  renderChecks(analysis.checks || []);

  const refWrap = document.getElementById('referenceWrap');
  if (analysis.referenceUrl) {
    document.getElementById('referenceButton').href = analysis.referenceUrl;
    refWrap.classList.remove('hidden');
  } else {
    refWrap.classList.add('hidden');
  }

  document.getElementById('submitFalsePositive').disabled = analysis.verdict === 'safe' || analysis.source === 'browser';
}

function renderFailure(message) {
  document.getElementById('verdictLabel').textContent = 'Denetim Başarısız';
  document.getElementById('hostname').textContent = message;
  document.getElementById('updatedAt').textContent = '-';
  document.getElementById('source').textContent = '-';
  document.getElementById('latency').textContent = '-';
  document.getElementById('scoreValue').textContent = '-';
  document.getElementById('confidenceValue').textContent = '-';
  document.getElementById('syncState').textContent = '-';
  document.getElementById('statsLine').textContent = 'Analiz yanıtı alınamadı.';
  updateVerdictIcon('unknown');
  document.getElementById('signals').innerHTML = '<li class="rich-list-empty">Bu sekme için analiz sonucu alınamadı.</li>';
  document.getElementById('checks').innerHTML = '<li class="rich-list-empty">Servis durumları görüntülenemiyor.</li>';
  renderDegraded({ degraded: ['backend'] });
}

async function submitReport(kind) {
  if (!activeAnalysis) return;
  const response = await fetch(`${currentSettings.apiBaseUrl.replace(/\/$/, '')}/report`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      url: activeAnalysis.targetUrl || `https://${activeAnalysis.normalizedUrl}`,
      kind,
      note: document.getElementById('reportNote').value.trim()
    })
  }).catch(() => null);

  if (response?.ok) {
    document.getElementById('reportStatus').textContent = kind === 'false_positive'
      ? 'Yanlış pozitif bildirimi gönderildi.'
      : 'Site raporu gönderildi.';
    return;
  }

  document.getElementById('reportStatus').textContent = 'Gönderim başarısız oldu.';
}

async function loadPopup() {
  const [analysisResponse, settingsResponse, statsResponse] = await Promise.all([
    sendMsg({ type: 'GET_ACTIVE_ANALYSIS' }),
    sendMsg({ type: 'GET_SETTINGS' }),
    sendMsg({ type: 'GET_STATS' })
  ]);

  if (settingsResponse?.ok) {
    currentSettings = settingsResponse.settings;
    document.body.dataset.theme = currentSettings.theme === 'light' ? 'light' : 'dark';
  }

  if (analysisResponse?.ok) {
    renderAnalysis(analysisResponse.analysis);
  } else {
    renderFailure(analysisResponse?.error || 'Yanıt alınamadı.');
  }

  if (statsResponse?.ok) {
    const stats = statsResponse.stats;
    document.getElementById('usageLine').textContent = `Bugün: ${stats.blockedToday} engel · ${stats.suspiciousToday} şüpheli · ${stats.unknownToday} belirsiz`;
  }
}

document.getElementById('openSettings').addEventListener('click', () => {
  invokeApiMethod(popupApi.tabs, 'create', { url: popupApi.runtime.getURL('settings.html') }).catch(() => {});
});

document.getElementById('scanBtn').addEventListener('click', async () => {
  const url = document.getElementById('scanInput').value.trim();
  const resultDiv = document.getElementById('scanResult');
  if (!url) return;
  resultDiv.className = 'scan-result';
  resultDiv.textContent = 'Analiz ediliyor...';
  const response = await sendMsg({ type: 'CHECK_LINK', url });
  if (response?.ok) {
    const appearance = verdictPresentation(response.analysis.verdict);
    resultDiv.textContent = `${appearance.text} · Skor ${response.analysis.score ?? 0}/100 · ${response.analysis.hostname}`;
    resultDiv.className = `scan-result scan-result-${response.analysis.verdict}`;
  } else {
    resultDiv.textContent = 'Analiz başarısız.';
    resultDiv.className = 'scan-result scan-result-unknown';
  }
});

document.getElementById('submitReport').addEventListener('click', () => submitReport('report'));
document.getElementById('submitFalsePositive').addEventListener('click', () => submitReport('false_positive'));

loadPopup().catch((error) => renderFailure(error.message));
