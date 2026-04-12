# ใช้ Image PHP ที่มี Apache มาให้เลย
FROM php:7.4-apache

# ติดตั้ง Extension สำหรับ MySQL และ Curl
RUN docker-php-ext-install mysqli pdo pdo_mysql
RUN apt-get update && apt-get install -y libcurl4-openssl-dev && docker-php-ext-install curl

# ก๊อปปี้ไฟล์งานทั้งหมดไปไว้ในโฟลเดอร์เว็บ
COPY . /var/www/html/

# เปิดพอร์ต 80
EXPOSE 80

