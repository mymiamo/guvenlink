const DB_NAME = 'guvenlink-db';
const DB_VERSION = 3;
const THREAT_STORE_NAMES = ['black-domain', 'black-url', 'white-domain', 'white-url', 'suspicious-domain', 'suspicious-url', 'meta'];
const CACHE_STORE = 'analysis-cache';

function openThreatDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);
    request.onupgradeneeded = () => {
      const db = request.result;
      [...THREAT_STORE_NAMES, CACHE_STORE].forEach((name) => {
        if (!db.objectStoreNames.contains(name)) {
          const store = db.createObjectStore(name, { keyPath: name === CACHE_STORE ? 'cacheKey' : 'value' });
          if (name === CACHE_STORE) store.createIndex('expiresAt', 'expiresAt', { unique: false });
        }
      });
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function clearThreatStores() {
  const db = await openThreatDb();
  try {
    await new Promise((resolve, reject) => {
      const writableStores = THREAT_STORE_NAMES.filter((name) => name !== 'meta');
      const tx = db.transaction(writableStores, 'readwrite');
      writableStores.forEach((name) => tx.objectStore(name).clear());
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function upsertThreatEntries(entries) {
  if (!entries?.length) return;
  const db = await openThreatDb();
  try {
    await new Promise((resolve, reject) => {
      const writableStores = THREAT_STORE_NAMES.filter((name) => name !== 'meta');
      const tx = db.transaction(writableStores, 'readwrite');
      for (const entry of entries) {
        const storeName = `${entry.status}-${entry.type}`;
        if (db.objectStoreNames.contains(storeName)) tx.objectStore(storeName).put(entry);
      }
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function removeThreatEntries(entries) {
  if (!entries?.length) return;
  const db = await openThreatDb();
  try {
    await new Promise((resolve, reject) => {
      const writableStores = THREAT_STORE_NAMES.filter((name) => name !== 'meta');
      const tx = db.transaction(writableStores, 'readwrite');
      for (const entry of entries) {
        writableStores.forEach((storeName) => tx.objectStore(storeName).delete(entry.value));
      }
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function lookupInBlocklist(hostname, normalizedUrl) {
  const db = await openThreatDb();
  const candidates = [
    { store: 'white-domain', value: hostname },
    { store: 'black-domain', value: hostname },
    { store: 'suspicious-domain', value: hostname },
    { store: 'white-url', value: normalizedUrl },
    { store: 'black-url', value: normalizedUrl },
    { store: 'suspicious-url', value: normalizedUrl }
  ];
  try {
    for (const candidate of candidates) {
      const result = await new Promise((resolve, reject) => {
        const tx = db.transaction([candidate.store], 'readonly');
        const req = tx.objectStore(candidate.store).get(candidate.value);
        req.onsuccess = () => resolve(req.result ?? null);
        req.onerror = () => reject(req.error);
      });
      if (result) return result;
    }
    return null;
  } finally {
    db.close();
  }
}

const ANALYSIS_CACHE_TTL_MS = 30 * 60 * 1000;

async function cacheAnalysis(cacheKey, analysis) {
  const db = await openThreatDb();
  try {
    await new Promise((resolve, reject) => {
      const tx = db.transaction([CACHE_STORE], 'readwrite');
      tx.objectStore(CACHE_STORE).put({ cacheKey, analysis, expiresAt: Date.now() + ANALYSIS_CACHE_TTL_MS });
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function getCachedAnalysis(cacheKey) {
  const db = await openThreatDb();
  try {
    const record = await new Promise((resolve, reject) => {
      const tx = db.transaction([CACHE_STORE], 'readonly');
      const req = tx.objectStore(CACHE_STORE).get(cacheKey);
      req.onsuccess = () => resolve(req.result ?? null);
      req.onerror = () => reject(req.error);
    });
    if (!record || record.expiresAt < Date.now()) return null;
    return record.analysis;
  } finally {
    db.close();
  }
}

async function setMeta(key, value) {
  const db = await openThreatDb();
  try {
    await new Promise((resolve, reject) => {
      const tx = db.transaction(['meta'], 'readwrite');
      tx.objectStore('meta').put({ value: key, payload: value });
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });
  } finally {
    db.close();
  }
}

async function getMeta(key) {
  const db = await openThreatDb();
  try {
    return await new Promise((resolve, reject) => {
      const tx = db.transaction(['meta'], 'readonly');
      const req = tx.objectStore('meta').get(key);
      req.onsuccess = () => resolve(req.result?.payload ?? null);
      req.onerror = () => reject(req.error);
    });
  } finally {
    db.close();
  }
}
