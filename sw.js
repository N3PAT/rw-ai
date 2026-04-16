// ดึงเวอร์ชันจาก URL หรือใช้ Timestamp เพื่อให้ชื่อ Cache ไม่ซ้ำกันเลยในแต่ละครั้งที่อัปเดต
const urlParams = new URLSearchParams(self.location.search);
const VERSION = urlParams.get('v') || Date.now(); // ถ้าไม่มี v ให้ใช้เวลาปัจจุบันเป็น Version ไปเลย
const CACHE_NAME = `rw-ai-v${VERSION}`;

const assets = [
  '/',
  'index.php',
  'manifest.json'
];

// 1. Install: บังคับให้ข้ามการรอ
self.addEventListener('install', event => {
  self.skipWaiting(); 
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(assets);
    })
  );
});

// 2. Activate: ลบแคชเก่าทันที (แบบโหด)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.map(key => {
          if (key !== CACHE_NAME) {
            console.log('RW-AI: Deleted Old Cache:', key);
            return caches.delete(key);
          }
        })
      );
    })
  );
  self.clients.claim(); 
});

// 3. Fetch: ใช้กลยุทธ์ Network First (พยายามเอาจากเน็ตก่อนเสมอ)
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request)
      .then(response => {
        // ถ้าโหลดจากเน็ตได้ ให้เก็บลงแคชใหม่ด้วย
        const resClone = response.clone();
        caches.open(CACHE_NAME).then(cache => {
          cache.put(event.request, resClone);
        });
        return response;
      })
      .catch(() => caches.match(event.request)) // ถ้าเน็ตล่ม ถึงจะไปเอาจากแคช
  );
});
