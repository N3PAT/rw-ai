# ใช้ Image PHP ที่มี Apache มาให้เลย
FROM php:7.4-apache

# 1. ติดตั้ง Extension สำหรับ MySQL และ Curl
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql curl

# 2. ก๊อปปี้ไฟล์งานทั้งหมดไปไว้ในโฟลเดอร์เว็บ
COPY . /var/www/html/

# 3. จัดการ Permission (แก้ไขปัญหา Permission Denied)
# สร้างโฟลเดอร์ uploads รอไว้ (เผื่อในเครื่องไม่มี)
RUN mkdir -p /var/www/html/gallery/uploads

# เปลี่ยนเจ้าของโฟลเดอร์ทั้งหมดให้เป็นของ www-data (User ที่ Apache ใช้รัน)
RUN chown -R www-data:www-data /var/www/html/

# ตั้งค่าสิทธิ์ให้สามารถเขียนไฟล์ได้ (775)
RUN chmod -R 775 /var/www/html/gallery/uploads

# 4. เปิดพอร์ต 80
EXPOSE 80
