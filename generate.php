<?php
// 1. รันไฟล์นี้เพื่อดูค่า Hash ที่ถูกต้องจากเครื่องคุณ
$pass = 'RW-Admin!@#2026_Secure';
echo "Copy ค่านี้ไปใส่ใน Database ช่อง password_hash : <br>";
echo password_hash($pass, PASSWORD_BCRYPT);
?>
