const DB_NAME = 'guvenlik-db';
const DB_VERSION = 1;
const STORE_NAMES = ['black-domain', 'black-url', 'white-domain', 'white-url', 'meta'];

function openThreatDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
      const db = request.result;
      STORE_NAMES.forEach((name) => {
        if (!db.objectStoreNames.contains(name)) {
          db.createObjectStore(name, { keyPath: 'value' });
        }
      });
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

async function clearThreatStores() {
  const db = await openThreatDb();
  await new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAMES, 'readwrite');
    STORE_NAMES.forEach((name) => tx.objectStore(name).clear());
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

async function putThreatEntries(entries) {
  if (!entries.length) {
    return;
  }

  const db = await openThreatDb();
  await new Promise((resolve, reject) => {
    const tx = db.transaction(STORE_NAMES, 'readwrite');
    for (const entry of entries) {
      const storeName = `${entry.status}-${entry.type}`;
      tx.objectStore(storeName).put(entry);
    }
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

async function setMeta(key, value) {
  const db = await openThreatDb();
  await new Promise((resolve, reject) => {
    const tx = db.transaction(['meta'], 'readwrite');
    tx.objectStore('meta').put({ value: key, payload: value });
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
  });
  db.close();
}

async function getMeta(key) {
  const db = await openThreatDb();
  const result = await new Promise((resolve, reject) => {
    const tx = db.transaction(['meta'], 'readonly');
    const request = tx.objectStore('meta').get(key);
    request.onsuccess = () => resolve(request.result ? request.result.payload : null);
    request.onerror = () => reject(request.error);
  });
  db.close();
  return result;
}

async function getThreatEntry(storeName, value) {
  const db = await openThreatDb();
  const result = await new Promise((resolve, reject) => {
    const tx = db.transaction([storeName], 'readonly');
    const request = tx.objectStore(storeName).get(value);
    request.onsuccess = () => resolve(request.result ?? null);
    request.onerror = () => reject(request.error);
  });
  db.close();
  return result;
}

