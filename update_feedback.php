<?php
// update_feedback.php
header('Content-Type: application/json');

/**
 * 1. ฟังก์ชันช่วยดึงค่า Config (ลองดึงจาก System ก่อน ถ้าไม่มีค่อยดูใน .env)
 */
function get_config($key, $default = null) {
    $val = getenv($key);
    if ($val !== false) return $val;
    
    // ถ้าไม่มีใน System ให้ลองอ่านจาก .env (เผื่อรัน Local)
    static $env_vars = null;
    if ($env_vars === null) {
        $env_path = __DIR__ . '/.env';
        $env_vars = is_readable($env_path) ? parse_ini_file($env_path) : [];
    }
    return isset($env_vars[$key]) ? $env_vars[$key] : $default;
}

// 2. ดึงค่า Config มาใช้งาน
$host = get_config('DB_HOST');
$port = (int)get_config('DB_PORT', 3306);
$user = get_config('DB_USER');
$pass = get_config('DB_PASS');
$db   = get_config('DB_NAME');
$ca_filename = get_config('DB_SSL_CA', 'ca.pem');

// กำหนด Path ของไฟล์ CA (Render มักจะวางไว้ที่เดียวกับโค้ด หรือใน /etc/secrets/)
$ca_path = __DIR__ . '/' . $ca_filename;

// ตรวจสอบข้อมูลเบื้องต้น
if (!$host || !$user) {
    die(json_encode([
        "status" => "error", 
        "message" => "Missing database configuration. Please check Render Environment Variables."
    ]));
}

// 3. เชื่อมต่อฐานข้อมูลด้วย mysqli แบบ SSL (สำหรับ Aiven)
$conn = mysqli_init();

// ตรวจสอบว่าไฟล์ CA อ่านได้ไหม (ถ้าไม่ได้จะข้าม SSL ไป ซึ่ง Aiven อาจจะปฏิเสธการเชื่อมต่อ)
if (is_readable($ca_path)) {
    mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
}

// เชื่อมต่อแบบ TCP/IP
$connected = @mysqli_real_connect($conn, $host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$connected) {
    die(json_encode([
        "status" => "error", 
        "message" => "Database Connection Failed: " . mysqli_connect_error()
    ]));
}

// 4. รับข้อมูล JSON จากหน้าเว็บ
$input = file_get_contents('php://input');
$data = json_decode($input, true);

$log_id = isset($data['log_id']) ? (int)$data['log_id'] : 0;
$rating = isset($data['rating']) ? (int)$data['rating'] : 0;

// 5. UPDATE ข้อมูลลงตาราง chat_logs
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
