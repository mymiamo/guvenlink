const DEFAULT_API_BASE_URL = 'http://localhost:8000/api';
const SYNC_ALARM_NAME = 'guvenlink-sync';
const SYNC_INTERVAL_MINUTES = 60;
const ALLOW_TTL_MS = 10 * 60 * 1000;
const RISKY_SHORTENERS = ['aylink.co', 'link.tr', 'tr.link', 'linkperisi.com', 'pnd.tl', 'adf.ly', 'clk.sh'];
const SAFE_SHORTENERS = ['bit.ly', 'tinyurl.com', 't.co', 'goo.gl', 'cutt.ly', 'shorturl.at', 'ow.ly', 'rb.gy', 'is.gd'];

const DEFAULT_SETTINGS = {
  apiBaseUrl: DEFAULT_API_BASE_URL,
  securityMode: 'balanced',
  offlineBehavior: 'unknown',
  telemetryEnabled: false,
  theme: 'dark'
};

function getExtensionApi() {
  return globalThis.browser ?? globalThis.chrome;
}

function isPromiseLike(value) {
  return !!value && typeof value.then === 'function';
}

function invokeApiMethod(target, methodName, ...args) {
  const method = target?.[methodName];
  if (typeof method !== 'function') {
    return Promise.reject(new Error(`API method unavailable: ${methodName}`));
  }

  const prefersPromiseApi = typeof globalThis.browser !== 'undefined';

  return new Promise((resolve, reject) => {
    let settled = false;

    const finish = (type, value) => {
      if (settled) return;
      settled = true;

      if (type === 'reject') {
        reject(value);
        return;
      }

      const lastError = getExtensionApi()?.runtime?.lastError;
      if (lastError) {
        reject(new Error(lastError.message || String(lastError)));
        return;
      }

      resolve(value);
    };

    try {
      if (prefersPromiseApi) {
        const result = method.call(target, ...args);

        if (isPromiseLike(result)) {
          result.then((value) => finish('resolve', value), (error) => finish('reject', error));
          return;
        }

        finish('resolve', result);
        return;
      }

      method.call(target, ...args, (value) => finish('resolve', value));
    } catch (error) {
      finish('reject', error);
    }
  });
}

function sendRuntimeMessage(message) {
  return invokeApiMethod(getExtensionApi().runtime, 'sendMessage', message);
}

function normalizeUrl(rawUrl) {
  const candidate = /^[a-z][a-z0-9+\-.]*:\/\//i.test(rawUrl) ? rawUrl : `https://${rawUrl}`;
  const parsed = new URL(candidate);
  const hostname = parsed.hostname.toLowerCase().replace(/\.$/, '');
  const pathname = decodeURIComponent(parsed.pathname || '/').replace(/\/+/g, '/');
  const search = parsed.search || '';

  return {
    originalUrl: parsed.toString(),
    hostname,
    normalizedUrl: `${hostname}${pathname || '/'}${search}`,
    scheme: parsed.protocol.replace(':', '').toLowerCase()
  };
}

function hostnameMatchesList(hostname, candidates) {
  return candidates.some((candidate) => hostname === candidate || hostname.endsWith(`.${candidate}`));
}

function shortenerCategory(hostname) {
  if (hostnameMatchesList(hostname, RISKY_SHORTENERS)) return 'risky';
  if (hostnameMatchesList(hostname, SAFE_SHORTENERS)) return 'safe';
  return null;
}

function heuristicSignals(rawUrl, normalized) {
  const signals = [];
  const hostname = normalized.hostname;
  const parts = hostname.split('.');
  const tld = parts[parts.length - 1] || '';

  if (rawUrl.toLowerCase().startsWith('http://')) {
    signals.push({
      source: 'heuristic',
      label: 'HTTP kullanımı',
      severity: 'low',
      code: 'HTTP',
      description: 'Bağlantı şifrelenmemiş HTTP kullanıyor.',
      weight: 15
    });
  }

  if (hostname.includes('xn--')) {
    signals.push({
      source: 'heuristic',
      label: 'Punycode / IDN',
      severity: 'medium',
      code: 'PUNYCODE',
      description: 'Punycode veya IDN alan adı tespit edildi.',
      weight: 25
    });
  }

  const shortenerType = shortenerCategory(hostname);
  if (shortenerType === 'risky') {
    signals.push({
      source: 'heuristic',
      label: 'Riskli kısa link servisi',
      severity: 'medium',
      code: 'SHORTENER_RISKY',
      description: 'Riskli link kısaltma servisi kullanılıyor.',
      weight: 40
    });
  } else if (shortenerType === 'safe') {
    signals.push({
      source: 'heuristic',
      label: 'Güvenilir kısa link servisi',
      severity: 'low',
      code: 'SHORTENER_SAFE',
      description: 'Güvenilir link kısaltma servisi kullanılıyor.',
      weight: 0
    });
  }

  if (normalized.normalizedUrl.length > 180 || /(%[0-9a-f]{2}){6,}/i.test(rawUrl)) {
    signals.push({
      source: 'heuristic',
      label: 'Gizlenmiş veya uzun URL',
      severity: 'medium',
      code: 'OBFUSCATED',
      description: 'URL gizlenmiş veya aşırı uzun görünüyor.',
      weight: 18
    });
  }

  if (parts.length > 5) {
    signals.push({
      source: 'heuristic',
      label: 'Aşırı alt alan adı',
      severity: 'low',
      code: 'SUBDOMAIN',
      description: 'Aşırı sayıda alt alan adı tespit edildi.',
      weight: 12
    });
  }

  if (['zip', 'mov', 'country', 'click', 'top', 'gq', 'tk', 'work', 'support', 'shop', 'xyz', 'rest', 'live', 'vip', 'cam'].includes(tld)) {
    signals.push({
      source: 'heuristic',
      label: 'Riskli TLD',
      severity: 'medium',
      code: 'TLD',
      description: `Riskli TLD tespit edildi (.${tld}).`,
      weight: 18
    });
  }

  return signals;
}

function heuristicReasons(rawUrl, normalized) {
  return heuristicSignals(rawUrl, normalized).map((signal) => signal.description);
}

function verdictPresentation(verdict) {
  switch (verdict) {
    case 'malicious':
      return { text: 'Zararlı', color: '#c62828', badge: '!' };
    case 'suspicious':
      return { text: 'Şüpheli', color: '#e67e22', badge: '?' };
    case 'unknown':
      return { text: 'Belirsiz', color: '#546e7a', badge: '…' };
    default:
      return { text: 'Güvenli', color: '#2e7d32', badge: '' };
  }
}
