const CACHE_NAME = 'rw-ai-v1';
const assets = [
  '/',
  'index.php',
  'manifest.json',
  // ใส่ชื่อไฟล์ CSS/JS ของน้องตรงนี้ เช่น:
];

// ติดตั้ง Service Worker และเก็บไฟล์ลง Cache
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      return cache.addAll(assets);
    })
  );
});

// เรียกใช้ไฟล์จาก Cache เมื่อ Offline (ถ้าเป็นไฟล์ AI จะให้ข้ามไปโหลดจากเน็ต)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});
