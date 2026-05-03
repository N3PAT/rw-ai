# ใช้ Image PHP ที่มี Apache มาให้เลย
FROM php:7.4-apache

# 1. ติดตั้ง Extension สำหรับ MySQL และ Curl
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql curl

# 2. ปรับแต่งค่า PHP สำหรับการอัปโหลดไฟล์จำนวนมาก (200 ไฟล์ / 3 นาที)
RUN { \
    echo 'upload_max_filesize = 500M'; \
    echo 'post_max_size = 500M'; \
    echo 'max_file_uploads = 250'; \
    echo 'max_execution_time = 180'; \
    echo 'memory_limit = 512M'; \
} > /usr/local/etc/php/conf.d/uploads.ini

# 3. ก๊อปปี้ไฟล์งานทั้งหมดไปไว้ในโฟลเดอร์เว็บ
COPY . /var/www/html/

# 4. จัดการ Permission (แก้ไขปัญหา Permission Denied)
RUN mkdir -p /var/www/html/gallery/uploads
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 775 /var/www/html/gallery/uploads

# 5. เปิดพอร์ต 80
EXPOSE 80
