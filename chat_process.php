<?php
declare(strict_types=1);

// เริ่ม Session สำหรับระบบ Rate Limit
session_start();

// --- 0. LOAD ENV ---
if (file_exists(__DIR__ . '/.env') && is_readable(__DIR__ . '/.env')) {
    $lines = @file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            putenv(sprintf('%s=%s', trim($name), trim($value)));
        }
    }
}

// --- 1. SETTINGS & ERROR HANDLING ---
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$config = [
    "db" => [
        "host" => getenv('DB_HOST'),
        "user" => getenv('DB_USER'),
        "pass" => getenv('DB_PASS'),
        "name" => getenv('DB_NAME'),
        "port" => (int)getenv('DB_PORT') 
    ],
    "gemini" => [
        "api_key" => getenv('GEMINI_API_KEY'),
        "model"   => getenv('GEMINI_MODEL')
    ]
];

function send_json(array $data): void {
    global $conn;
    if (isset($conn) && $conn instanceof mysqli) {
        @$conn->close();
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 🛡️ ระบบจัดการคิว (Request Throttling) - ปรับเป็น 20 เพื่อให้นัททดสอบได้ลื่นขึ้น

// --- 2. DATABASE CONNECTION ---
$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 
$success = $conn->real_connect($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name'], $config['db']['port'], NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    send_json(["status" => "error", "response" => "ระบบฐานข้อมูลขัดข้องชั่วคราวครับ พี่กำลังรีบซ่อมอยู่!", "log_id" => null]);
}
$conn->set_charset("utf8mb4");

// --- ⚡ 2.5 QUICK RESPONSE (ถูกนำออกเพื่อให้ AI ประมวลผลเองทั้งหมด) ---

// --- 3. SMART LOAD ENGINE ---
$context = "";

// ใช้ Full-Text Search ค้นหาข้อมูลที่เกี่ยวข้องที่สุด 15 อันดับแรก
$searchQuery = "SELECT info_key, info_value_th FROM school_profile WHERE MATCH(info_key, info_value_th) AGAINST(?) LIMIT 15";
$stmt = $conn->prepare($searchQuery);
$stmt->bind_param("s", $userMessageRaw);
$stmt->execute();
$resProfile = $stmt->get_result();

if ($resProfile && $resProfile->num_rows > 0) {
    while ($row = $resProfile->fetch_assoc()) {
        $context .= "- {$row['info_key']}: {$row['info_value_th']}\n";
    }
} else {
    // กรณีค้นหาแบบ Full-text ไม่เจอ ให้ลองใช้ LIKE แบบกว้างๆ เป็นแผนสำรอง
    $likeQuery = "SELECT info_key, info_value_th FROM school_profile WHERE info_key LIKE ? OR info_value_th LIKE ? LIMIT 5";
    $searchTerm = "%$userMessageRaw%";
    $stmt2 = $conn->prepare($likeQuery);
    $stmt2->bind_param("ss", $searchTerm, $searchTerm);
    $stmt2->execute();
    $resLike = $stmt2->get_result();
    while ($row = $resLike->fetch_assoc()) {
        $context .= "- {$row['info_key']}: {$row['info_value_th']}\n";
    }
}

$context = mb_substr($context, 0, 2000, 'UTF-8');

// --- 4. PROMPT CONSTRUCTION ---
$prompt = "คุณคือ 'พี่ RW-AI' รุ่นพี่จากโรงเรียนฤทธิยะวรรณาลัย ตอบคำถามน้องๆ ด้วยความเป็นกันเอง อบอุ่น และสุภาพ

[ข้อมูลอ้างอิงจากโรงเรียน]:
$context

[กฎการตอบ]
1. แทนตัวเองว่า 'พี่' และเรียกผู้ถามว่า 'น้อง' หรือ 'น้องๆ'
2. หากคำถามเกี่ยวข้องกับแผนผังโรงเรียน หรือถามว่าอาคารไหนอยู่ที่ไหน ให้ตอบรายละเอียดแล้วปิดท้ายด้วยสัญลักษณ์ [SHOW_MAP] เสมอ
3. หากข้อมูลใน [ข้อมูลอ้างอิง] ไม่มี ให้ตอบอย่างใจดีว่า 'พี่หาข้อมูลส่วนนี้ให้น้องไม่ได้จริงๆ ครับ ลองสอบถามคุณครูประชาสัมพันธ์ดูนะ' ห้ามเดาข้อมูลเอง
4. เรื่องคะแนนพฤติกรรม: ย้ำว่าโควตาต่อ ม.4 ต้องมีไม่ต่ำกว่า 60 คะแนน (10 ความดี = 1 พฤติกรรม)
5. ลงท้ายด้วย 'ครับ' ทุกประโยคเพื่อให้ดูสุภาพและเป็นทางการในแบบรุ่นพี่
6. การแสดงรูปภาพ: 
   - หากถามถึงแผนผัง ให้ตอบข้อความแล้วปิดท้ายด้วย [SHOW_MAP] เท่านั้น ห้ามใส่ URL หรือคำสั่งอื่น
   - หากมีรูปภาพจากฐานข้อมูล ให้ใช้รูปแบบ [SHOW_IMG:URL] 
   - **ห้ามใส่เครื่องหมายคำพูด หรือวงเล็บปิด หรือ onerror เด็ดขาด**

คำถามจากน้อง: {$userMessageSafe}
คำตอบจากพี่ RW-AI:";

// --- 5. AI API CALL ---
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$config['gemini']['model']}:generateContent?key=" . urlencode((string)$config['gemini']['api_key']);
$payload = [
    "contents" => [["parts" => [["text" => $prompt]]]], 
    "generationConfig" => [
        "temperature" => 0.1, 
        "maxOutputTokens" => 1024
    ]
];

$success = false;
$retryCount = 0;
$aiResponse = "";
$httpCode = 0;

while (!$success && $retryCount < 2) {
    $ch = curl_init($apiUrl);
    
    // ตั้งค่า cURL Options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    
    // หากโฮสติ้งมีปัญหาเรื่อง SSL Certificate ให้ปลดคอมเมนต์ 2 บรรทัดข้างล่างนี้ (แต่ถ้าใช้ได้ปกติก็ไม่ต้อง)
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);

    if ($httpCode === 200 && $rawResponse !== false) {
        $resData = json_decode($rawResponse, true);
        $aiResponse = $resData['candidates'][0]['content']['parts'][0]['text'] ?? "";
        
        if (!empty($aiResponse)) {
            $success = true;
        } else {
            $retryCount++;
        }
    } else {
        $retryCount++;
        if ($retryCount < 2) {
            usleep(500000); // พัก 0.5 วินาทีก่อนลองใหม่
        }
    }
}

// --- 6. FINAL OUTPUT ---
if ($isOk) {
    send_json(["status" => "success", "response" => trim($aiResponse), "log_id" => (string)time()]);
} else {
    $msg = ($res['httpCode'] === 503) ? "ตอนนี้คนใช้บริการเยอะมากครับ พี่ประมวลผลไม่ทัน ลองอีก 10 วิ นะครับ" : "พี่ขอโทษครับ ระบบขัดข้อง (Error: {$res['httpCode']})";
    send_json(["status" => "error", "response" => $msg, "log_id" => null]);
}
