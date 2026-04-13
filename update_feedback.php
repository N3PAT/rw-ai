<?php
// update_feedback.php
header('Content-Type: application/json');

// 1. ระบุตำแหน่งไฟล์ .env และ ca.pem ให้ชัดเจน
// ใช้ __DIR__ เพื่อให้ PHP วิ่งหาจากโฟลเดอร์ที่ไฟล์นี้อยู่
$env_path = __DIR__ . '/.env';

if (!is_readable($env_path)) {
    die(json_encode([
        "status" => "error", 
        "message" => "Permission denied: ไม่สามารถอ่านไฟล์ .env ได้ กรุณาเช็ค chmod"
    ]));
}

// โหลดค่าจากไฟล์ .env
$env = parse_ini_file($env_path);

// ตรวจสอบว่าโหลดค่าสำคัญมาได้ครบไหม
if (!$env || !isset($env['DB_HOST'])) {
    die(json_encode(["status" => "error", "message" => "โหลดค่าจาก .env ไม่สำเร็จ หรือไฟล์ว่างเปล่า"]));
}

$host = $env['DB_HOST'];
$port = (int)$env['DB_PORT'];
$user = $env['DB_USER'];
$pass = $env['DB_PASS'];
$db   = $env['DB_NAME'];
$ca   = __DIR__ . '/' . $env['DB_SSL_CA']; // ระบุพาธเต็มให้ไฟล์ CA ด้วย

// 2. เชื่อมต่อฐานข้อมูลด้วย mysqli แบบ SSL (สำหรับ Aiven)
$conn = mysqli_init();

// ตรวจสอบว่าไฟล์ CA มีจริงและอ่านได้ไหม
if (!is_readable($ca)) {
    die(json_encode(["status" => "error", "message" => "ไม่พบไฟล์ SSL CA (ca.pem) หรืออ่านไม่ได้"]));
}

mysqli_ssl_set($conn, NULL, NULL, $ca, NULL, NULL);

// เพิ่มสถานะการเชื่อมต่อแบบ TCP/IP เพื่อป้องกัน Error: No such file or directory
if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database Connection Failed: " . mysqli_connect_error()
    ]));
}

// 3. รับข้อมูล JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$log_id = isset($data['log_id']) ? (int)$data['log_id'] : 0;
$rating = isset($data['rating']) ? (int)$data['rating'] : 0;

// 4. UPDATE ลงตาราง
if ($log_id > 0 && ($rating === 1 || $rating === -1)) {
    $stmt = $conn->prepare("UPDATE chat_logs SET rating = ? WHERE id = ?");
    $stmt->bind_param("ii", $rating, $log_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success", 
            "log_id" => $log_id,
            "rating" => $rating
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "SQL Execute failed"]);
    }
    $stmt->close();
} else {
    echo json_encode(["status" => "invalid_params", "data_received" => $data]);
}

$conn->close();
