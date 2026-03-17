const DEFAULT_API_BASE_URL = 'https://your-domain.example/guvenlink/backend/public/api';
const SYNC_ALARM_NAME = 'guvenlik-sync';
const SYNC_INTERVAL_MINUTES = 1;
const ALLOW_TTL_MS = 10 * 60 * 1000;

function getExtensionApi() {
  return globalThis.browser ?? globalThis.chrome;
}

function normalizeUrl(rawUrl) {
  const candidate = /^[a-z][a-z0-9+\-.]*:\/\//i.test(rawUrl) ? rawUrl : `https://${rawUrl}`;
  const parsed = new URL(candidate);
  const hostname = parsed.hostname.toLowerCase().replace(/\.$/, '');
  const path = parsed.pathname || '/';
  const search = parsed.search || '';

  return {
    hostname,
    normalizedUrl: `${hostname}${path}${search}`,
    scheme: parsed.protocol.replace(':', '').toLowerCase(),
  };
}

function heuristicReasons(rawUrl, normalized) {
  const reasons = [];
  const hostname = normalized.hostname;

  if (rawUrl.toLowerCase().startsWith('http://')) {
    reasons.push('Baglanti sifrelenmemis HTTP kullaniyor.');
  }

  if (hostname.includes('xn--')) {
    reasons.push('Punycode veya IDN alan adi tespit edildi.');
  }

  if (['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'shorturl.at'].includes(hostname)) {
    reasons.push('Link kisaltma servisi kullaniliyor.');
  }

  const suspiciousTlds = ['zip', 'mov', 'country', 'click', 'top', 'gq', 'tk', 'work', 'support', 'shop', 'xyz'];
  const parts = hostname.split('.');
  const tld = parts[parts.length - 1] || '';
  if (suspiciousTlds.includes(tld)) {
    reasons.push('Riskli TLD tespiti yapildi.');
  }

  if (normalized.normalizedUrl.length > 180 || /(%[0-9a-f]{2}){6,}/i.test(rawUrl)) {
    reasons.push('URL gizlenmis veya asiri uzun gorunuyor.');
  }

  return reasons;
}

function verdictPresentation(verdict) {
  switch (verdict) {
    case 'malicious':
      return { text: 'Zararli', color: '#c62828', badge: '!' };
    case 'suspicious':
      return { text: 'Supheli', color: '#e67e22', badge: '?' };
    default:
      return { text: 'Guvenli', color: '#2e7d32', badge: 'OK' };
  }
}
