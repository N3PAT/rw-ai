<?php
// update_feedback.php
header('Content-Type: application/json');

// 1. โหลดค่าจากไฟล์ .env
$env = parse_ini_file('.env');

$host = $env['DB_HOST'];
$port = $env['DB_PORT'];
$user = $env['DB_USER'];
$pass = $env['DB_PASS'];
$db   = $env['DB_NAME'];
$ca   = $env['DB_SSL_CA'];

// 2. เชื่อมต่อฐานข้อมูลด้วย mysqli แบบ SSL (สำหรับ Aiven)
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $ca, NULL, NULL);

if (!mysqli_real_connect($conn, $host, $user, $pass, $db, $port)) {
    die(json_encode([
        "status" => "error", 
        "message" => "Connect Error: " . mysqli_connect_error()
    ]));
}

// 3. รับข้อมูล JSON ที่ส่งมาจาก JavaScript (fetch)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$log_id = isset($data['log_id']) ? (int)$data['log_id'] : 0;
$rating = isset($data['rating']) ? (int)$data['rating'] : 0;

// 4. ตรวจสอบข้อมูลและ UPDATE ลงตาราง chat_logs
if ($log_id > 0 && ($rating === 1 || $rating === -1)) {
    
    $stmt = $conn->prepare("UPDATE chat_logs SET rating = ? WHERE id = ?");
    $stmt->bind_param("ii", $rating, $log_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "success", 
            "message" => "Feedback updated",
            "log_id" => $log_id,
            "rating" => $rating
        ]);
    } else {
        echo json_encode(["status" => "error", "message" => "Execute failed"]);
    }
    
    $stmt->close();
} else {
    echo json_encode(["status" => "invalid_data", "received" => $data]);
}

$conn->close();
?>
