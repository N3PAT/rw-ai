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
        "models"  => [
            getenv('GEMINI_MODEL_PRIMARY') ?: 'gemini-3.1-flash-lite-preview', // ตัวหลัก
            getenv('GEMINI_MODEL_SECONDARY') ?: 'gemini-2.5-flash',           // ตัวสำรอง 1
            'gemini-1.5-flash'                                               // ตัวสำรองสุดท้าย (กันตาย)
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

// รับข้อมูลจาก Index.php
$input = json_decode(file_get_contents('php://input'), true);
$userMessageRaw = trim((string)($input['message'] ?? ''));
$userMessageSafe = htmlspecialchars(mb_substr($userMessageRaw, 0, 500, 'UTF-8'), ENT_QUOTES, 'UTF-8');

if ($userMessageSafe === '') {
    send_json(["status" => "error", "response" => "พิมพ์คำถามมาได้เลยครับ พี่ RW-AI รออยู่!", "log_id" => null]);
}

// --- 2. DATABASE CONNECTION ---
$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 
$db_success = $conn->real_connect($config['db']['host'], $config['db']['user'], $config['db']['pass'], $config['db']['name'], $config['db']['port'], NULL, MYSQLI_CLIENT_SSL);

if (!$db_success) {
    send_json(["status" => "error", "response" => "ระบบฐานข้อมูลขัดข้องชั่วคราวครับ พี่กำลังรีบซ่อมอยู่!", "log_id" => null]);
}
$conn->set_charset("utf8mb4");

// --- 3. SMART LOAD ENGINE ---
$context = "";

// ใช้ Full-Text Search (ต้องมั่นใจว่า Table ได้ทำ Full-text Index ไว้ที่ info_key, info_value_th แล้ว)
$searchQuery = "SELECT info_key, info_value_th FROM school_profile WHERE MATCH(info_key, info_value_th) AGAINST(?) LIMIT 15";
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
        // แผนสำรอง: LIKE
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

// --- 4. PROMPT CONSTRUCTION (ฉบับเน้นส่งแผนผังเมื่อไม่รู้เส้นทาง) ---
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


// --- 5. AI API CALL (Fixed 400/404/Missing Variables) ---
$success = false;
$aiResponse = "";
$httpCode = 0;
$rawResponse = ""; // เตรียมไว้เผื่อใช้ Debug ในส่วนที่ 6

// สร้างโครงสร้างข้อมูลที่จะส่งไป Google (ต้องอยู่นอก foreach เพื่อความเร็ว)
$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ], 
    "generationConfig" => [
        "temperature" => 0.2, 
        "maxOutputTokens" => 1024,
        "topP" => 0.8,
        "topK" => 10
    ]
];

foreach ($config['gemini']['models'] as $index => $currentModel) {
    if (!$currentModel) continue;

    $currentModel = trim($currentModel);
    $cleanModelName = str_replace('models/', '', $currentModel);
    
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModelName}:generateContent?key=" . urlencode((string)$config['gemini']['api_key']);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12); 
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $rawResponse = (string)curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $rawResponse !== "") {
        $resData = json_decode($rawResponse, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break; 
        }
    }

    // ถ้าไม่สำเร็จ ให้รอแป๊บนึงแล้วลองรุ่นถัดไป
    if (!$success && $index < count($config['gemini']['models']) - 1) {
        usleep(300000); 
    }
}


// --- 6. FINAL OUTPUT ---
if ($success) {
    // บันทึกลง Chat Log (ตัวอย่างการสร้าง log_id)
    $logId = (string)time(); 
    send_json([
        "status" => "success", 
        "response" => trim($aiResponse), 
        "log_id" => $logId
    ]);
} else {
    $errorMsg = ($httpCode === 503) ? "ตอนนี้คนใช้บริการเยอะมากครับ พี่ประมวลผลไม่ทัน ลองอีก 10 วิ นะครับ" : "พี่ขอโทษครับ ระบบขัดข้องชั่วคราว (Error: $httpCode)";
    send_json([
        "status" => "error", 
        "response" => $errorMsg, 
        "log_id" => null
    ]);
}
