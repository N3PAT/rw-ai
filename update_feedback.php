<?php
// update_feedback.php
header('Content-Type: application/json');

/**
 * 1. ดึงค่า Config จาก Environment Variables (สำหรับ Render)
 */
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$ca_file = getenv('DB_SSL_CA') ?: 'ca.pem'; 

// Path ของไฟล์ CA (บน Render ถ้าใส่ใน Secret Files จะอยู่ที่เดียวกับไฟล์ PHP)
$ca_path = __DIR__ . '/' . $ca_file;

// เช็คว่ามีค่าครบไหม
if (!$host || !$user) {
    die(json_encode(["status" => "error", "message" => "Environment variables missing"]));
}

// 2. เชื่อมต่อ DB
$conn = mysqli_init();
if (file_exists($ca_path)) {
    mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
}

// เชื่อมต่อแบบบังคับ SSL
$success = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}
// เพิ่มไว้ก่อนบรรทัด $log_id = ...
$raw_input = file_get_contents('php://input');
if (empty($raw_input)) {
    die(json_encode(["status" => "error", "message" => "ไม่มีข้อมูลดิบส่งมาเลย (Raw input is empty)"]));
}

// 3. รับข้อมูลจากหน้าบ้าน (JSON)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$log_id = isset($data['log_id']) ? (int)$data['log_id'] : 0;
$rating = isset($data['rating']) ? (int)$data['rating'] : 0;

// 4. อัปเดตข้อมูล
if ($log_id > 0 && ($rating === 1 || $rating === -1)) {
    $stmt = $conn->prepare("UPDATE chat_logs SET rating = ? WHERE id = ?");
    $stmt->bind_param("ii", $rating, $log_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "log_id" => $log_id]);
    } else {
        echo json_encode(["status" => "error", "message" => "Update failed"]);
    }
    $stmt->close();
} else {
    // ถ้ามาถึงตรงนี้ แสดงว่า JSON ที่ส่งมาจากหน้าบ้านมีปัญหา
    echo json_encode(["status" => "invalid_params", "data_received" => $data]);
}

$conn->close();
