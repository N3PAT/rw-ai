<?php
// update_feedback.php
header('Content-Type: application/json');

// 1. ดึงค่า Config
$host = getenv('DB_HOST');
$port = (int)getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$db   = getenv('DB_NAME');
$ca_file = getenv('DB_SSL_CA') ?: 'ca.pem'; 
$ca_path = __DIR__ . '/' . $ca_file;

if (!$host || !$user) {
    die(json_encode(["status" => "error", "message" => "Environment variables missing"]));
}

// 2. อ่านข้อมูลดิบ (อ่านแค่ "ครั้งเดียว" แล้วเก็บใส่ตัวแปรไว้)
$raw_input = file_get_contents('php://input');

if (empty($raw_input)) {
    die(json_encode(["status" => "error", "message" => "ไม่มีข้อมูลดิบส่งมาเลย (Raw input is empty)"]));
}

// 3. แปลง JSON จากตัวแปรที่เราเก็บไว้
$data = json_decode($raw_input, true);

if ($data === null) {
    die(json_encode(["status" => "error", "message" => "JSON Decode Error", "raw" => $raw_input]));
}

// 4. เชื่อมต่อ DB
$conn = mysqli_init();
if (file_exists($ca_path)) {
    mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
}

$success = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die(json_encode(["status" => "error", "message" => "DB Connection Failed"]));
}

// 5. รับค่า ID และ Rating
$log_id = isset($data['log_id']) ? (int)$data['log_id'] : 0;
$rating = isset($data['rating']) ? (int)$data['rating'] : 0;

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
    echo json_encode(["status" => "invalid_params", "data_received" => $data]);
}

$conn->close();
