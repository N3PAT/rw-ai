// ดึงเวอร์ชันจาก URL (ถ้าไม่มีให้ใช้ Default)
const urlParams = new URLSearchParams(self.location.search);
const VERSION = urlParams.get('v') || '1.0.78-G31L';
const CACHE_NAME = `rw-ai-v${VERSION}`;

const assets = [
  '/',
  'index.php',
  'manifest.json'
];

// 1. ติดตั้ง Service Worker
self.addEventListener('install', event => {
  // บังคับให้ SW ตัวใหม่ทำงานทันที ไม่ต้องรอให้ User ปิดเบราว์เซอร์
  self.skipWaiting(); 
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => {
      console.log('RW-AI: Caching system files');
      return cache.addAll(assets);
    })
  );
});

// 2. ล้างแคชเก่า (สำคัญมาก!)
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME) // ถ้าชื่อแคชไม่ตรงกับเวอร์ชันปัจจุบัน
            .map(key => {
              console.log('RW-AI: Clearing old cache:', key);
              return caches.delete(key); // ลบทิ้งเลย
            })
      );
    })
  );
  // ควบคุมทุก Tab ทันทีที่อัปเดตเสร็จ
  self.clients.claim(); 
});

// 3. การดึงข้อมูล (Fetch)
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      // ถ้ามีในแคชให้ใช้จากแคช ถ้าไม่มีให้โหลดจากเน็ต
      // ข้อดี: ช่วยให้หน้าเว็บหลักโหลดไวมาก
      return response || fetch(event.request);
    })
  );
});
