<?php
declare(strict_types=1);

// เริ่ม Session สำหรับระบบ Rate Limit
session_start();

// --- 0. LOAD ENV ---
// ตรวจสอบไฟล์ .env เฉพาะเวลาที่รันในเครื่องตัวเอง (Local) 
// ถ้าอยู่บน Render ระบบจะใช้ Environment Variables ที่เราตั้งค่าไว้ใน Dashboard โดยอัตโนมัติ
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
ini_set('display_errors', '0'); // ปิดการพ่น Error ออกหน้าจอเพื่อไม่ให้ JSON พัง
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$config = [
    "db" => [
        "host" => getenv('DB_HOST'),
        "user" => getenv('DB_USER'),
        "pass" => getenv('DB_PASS'),
        "name" => getenv('DB_NAME'),
        "port" => (int)(getenv('DB_PORT') ?: 3306) 
    ],
    "gemini" => [
        "api_key" => getenv('GEMINI_API_KEY'),
        "models" => [
            // ดึงจาก Env ถ้าไม่มีให้ใช้ Flash 1.5 เป็นค่าเริ่มต้น
            getenv('GEMINI_MODEL_PRIMARY') ?: 'gemini-1.5-flash'
        ]
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

// รับข้อมูลจาก Frontend
$input = json_decode(file_get_contents('php://input'), true);
$userMessageRaw = trim((string)($input['message'] ?? ''));
$userMessageSafe = htmlspecialchars(mb_substr($userMessageRaw, 0, 500, 'UTF-8'), ENT_QUOTES, 'UTF-8');

if ($userMessageSafe === '') {
    send_json(["status" => "error", "response" => "พิมพ์คำถามมาได้เลยครับ พี่ RW-AI รออยู่!", "log_id" => null]);
}

// --- 2. DATABASE CONNECTION ---
$conn = mysqli_init();
// หากใช้ Aiven อย่าลืมตรวจสอบเรื่อง SSL Certificate ถ้าจำเป็น
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 
$db_success = @$conn->real_connect($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name'], $config['db']['port'], NULL, MYSQLI_CLIENT_SSL);

if (!$db_success) {
    send_json(["status" => "error", "response" => "ระบบฐานข้อมูลขัดข้อง (Database Error)", "log_id" => null]);
}
$conn->set_charset("utf8mb4");

// --- 3. SMART LOAD ENGINE ---
$context = "";
$searchQuery = "SELECT info_key, info_value_th FROM school_profile WHERE MATCH(info_key, info_value_th) AGAINST(?) LIMIT 10";
$stmt = $conn->prepare($searchQuery);
if ($stmt) {
    $stmt->bind_param("s", $userMessageRaw);
    $stmt->execute();
    $resProfile = $stmt->get_result();
    if ($resProfile && $resProfile->num_rows > 0) {
        while ($row = $resProfile->fetch_assoc()) {
            $context .= "- {$row['info_key']}: {$row['info_value_th']}\n";
        }
    } else {
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
}

$context = mb_substr($context, 0, 2000, 'UTF-8');

// --- 4. PROMPT CONSTRUCTION (ห้ามตัดออกตามคำขอ) ---
$prompt = "คุณคือ 'พี่ RW-AI' รุ่นพี่จากโรงเรียนฤทธิยะวรรณาลัย

[ข้อมูลอ้างอิง]:
" . ($context ?: "ไม่มีข้อมูลเฉพาะเจาะจง") . "

[กฎเหล็กที่ต้องทำตาม]
1. แทนตัวเองว่า 'พี่' เรียกน้องว่า 'น้อง' และลงท้ายด้วย 'ครับ' ทุกประโยค
2. **การตอบเรื่องสถานที่/เส้นทาง**: 
   - หากน้องถามทาง หรือถามว่าอาคารไหนอยู่ที่ไหน ให้ตรวจเช็คจาก [ข้อมูลอ้างอิง] 
   - **ถ้าในข้อมูลไม่มีบอกเส้นทางที่ชัดเจน** ให้ตอบว่า 'พี่ไม่มีข้อมูลเส้นทางเป๊ะๆ แต่น้องลองดูจากแผนผังโรงเรียนที่พี่แนบให้ด้านล่างนี้นะครับ' แล้วปิดท้ายด้วย [SHOW_MAP] ทันที
   - หากคำถามมีคำว่า 'แผนผัง' หรือ 'ไปทางไหน' ให้ปิดท้ายด้วย [SHOW_MAP] เสมอ
3. หากหาข้อมูลไม่ได้จริงๆ ให้ตอบอย่างสุภาพว่า 'พี่หาข้อมูลส่วนนี้ให้ไม่ได้จริงๆ ครับ ลองถามคุณครูประชาสัมพันธ์ดูนะ'
4. เรื่องโควตา ม.4: ต้องมีคะแนนพฤติกรรมไม่ต่ำกว่า 60 คะแนน
5. ห้ามใช้ HTML/Markdown Link/Javascript ให้ใช้แค่ข้อความปกติและสัญลักษณ์ [SHOW_MAP] หรือ [SHOW_IMG:URL] เท่านั้น

คำถามจากน้อง: {$userMessageSafe}
คำตอบจากพี่ RW-AI:";

// --- 5. AI API CALL ---
$success = false;
$aiResponse = "";
$httpCode = 0;
$curlError = "";

$payload = [
    "contents" => [["parts" => [["text" => $prompt]]]], 
    "generationConfig" => [
        "temperature" => 0.4, 
        "maxOutputTokens" => 800
    ]
];

foreach ($config['gemini']['models'] as $currentModel) {
    if (!$currentModel) continue;

    $modelName = trim(str_replace('models/', '', $currentModel));
    $apiKey = (string)$config['gemini']['api_key'];
    
    // ตรวจสอบว่ามี API Key หรือไม่
    if (empty($apiKey)) {
        $curlError = "Missing API Key";
        break;
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=" . $apiKey;

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
    }
    curl_close($ch);

    if ($httpCode === 200 && $rawResponse) {
        $resData = json_decode($rawResponse, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break; 
        }
    }
}

// --- 6. FINAL OUTPUT ---
if ($success) {
    send_json([
        "status" => "success", 
        "response" => trim($aiResponse), 
        "log_id" => (string)time()
    ]);
} else {
    // ส่ง Error กลับไปให้ Frontend ประมวลผล
    $errorMsg = "พี่ขอโทษครับ ระบบขัดข้อง (Code: $httpCode)";
    if (!empty($curlError)) $errorMsg .= " | Connection Error: $curlError";
    
    send_json([
        "status" => "error", 
        "response" => $errorMsg, 
        "log_id" => null
    ]);
}
