const warningApi = getExtensionApi();
const params = new URLSearchParams(window.location.search);
const target = params.get('target') || '';
const payload = parsePayload(params.get('payload'));

function parsePayload(rawPayload) {
  try {
    return JSON.parse(rawPayload || '{}');
  } catch {
    return {};
  }
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

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function renderRichList(targetId, items, emptyText) {
  const list = document.getElementById(targetId);
  list.innerHTML = '';
  if (!items.length) {
    list.innerHTML = `<li class="rich-list-empty">${emptyText}</li>`;
    return;
  }

  items.forEach((item) => {
    const li = document.createElement('li');
    li.className = 'rich-list-item';
    li.innerHTML = `<strong>${escapeHtml(item.title)}</strong><span class="small muted">${escapeHtml(item.meta)}</span><p>${escapeHtml(item.description)}</p>`;
    list.appendChild(li);
  });
}

document.getElementById('targetUrl').textContent = target;
document.getElementById('warningVerdict').textContent = verdictPresentation(payload.verdict || 'unknown').text;
document.getElementById('warningScore').textContent = `${payload.score ?? 0}/100`;
document.getElementById('warningConfidence').textContent = confidenceLabel(payload.confidence);
document.getElementById('warningLatency').textContent = payload.latencyMs ? `${payload.latencyMs} ms` : '-';
document.getElementById('warningSource').textContent = sourceLabel(payload.source);
document.getElementById('warningUpdatedAt').textContent = payload.updatedAt || '-';

if (payload.usomDetails?.category || payload.usomDetails?.connectionType) {
  document.getElementById('warningUsomCategory').textContent = payload.usomDetails.category || '-';
  document.getElementById('warningUsomType').textContent = payload.usomDetails.connectionType || '-';
  document.getElementById('warningUsomDetails').classList.remove('hidden');
}

if (payload.degraded?.length) {
  const degraded = document.getElementById('warningDegraded');
  degraded.textContent = `Bazı servisler bu denetimde yanıt vermedi: ${payload.degraded.join(', ')}.`;
  degraded.classList.remove('hidden');
}

renderRichList(
  'warningReasons',
  (payload.signals?.length ? payload.signals : (payload.reasons || []).map((reason) => ({
    label: 'Analiz notu',
    source: payload.source,
    description: reason,
    severity: 'medium'
  }))).map((signal) => ({
    title: signal.label || 'Tespit sinyali',
    meta: `${signal.source || 'analiz'} · ${signal.severity || 'bilgi'}`,
    description: signal.description || '-'
  })),
  'Bu sayfa için ek tespit notu bulunmuyor.'
);

renderRichList(
  'warningChecks',
  (payload.checks || []).map((check) => ({
    title: serviceLabel(check.service),
    meta: `${statusLabel(check.status)}${check.latencyMs ? ` · ${check.latencyMs} ms` : ''}`,
    description: check.label || (check.matched ? 'Eşleşme bulundu.' : 'Servis kontrolü tamamlandı.')
  })),
  'Servis kontrol kaydı bulunmuyor.'
);

document.getElementById('goBack').addEventListener('click', async () => {
  try {
    const [tab] = await invokeApiMethod(warningApi.tabs, 'query', { active: true, currentWindow: true });
    if (tab?.id) await invokeApiMethod(warningApi.tabs, 'remove', tab.id);
    else window.close();
  } catch {
    window.close();
  }
});

document.getElementById('continueAnyway').addEventListener('click', async () => {
  await sendRuntimeMessage({ type: 'CONTINUE_TO_SITE', url: target });
  window.location.assign(target);
});

document.getElementById('reportFalsePositive').addEventListener('click', async () => {
  const ok = await sendRuntimeMessage({
    type: 'REPORT_FALSE_POSITIVE',
    target,
    note: 'Uyarı ekranından yanlış pozitif bildirimi gönderildi.'
  }).catch(() => null);
  document.getElementById('falsePositiveStatus').textContent = ok?.ok
    ? 'Geri bildirim gönderildi.'
    : 'Gönderim başarısız.';
});
