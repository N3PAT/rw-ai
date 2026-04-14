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

// --- 4. PROMPT CONSTRUCTION ---
$prompt = "คุณคือ 'พี่ RW-AI' รุ่นพี่จากโรงเรียนฤทธิยะวรรณาลัย ตอบคำถามน้องๆ ด้วยความเป็นกันเอง อบอุ่น และสุภาพ

[ข้อมูลอ้างอิงจากโรงเรียน]:
" . ($context ?: "ไม่มีข้อมูลเฉพาะเจาะจงในฐานข้อมูล") . "

[กฎการตอบ]
1. แทนตัวเองว่า 'พี่' และเรียกผู้ถามว่า 'น้อง' หรือ 'น้องๆ'
2. หากคำถามเกี่ยวข้องกับแผนผังโรงเรียน หรือถามว่าอาคารไหนอยู่ที่ไหน ให้ตอบรายละเอียดแล้วปิดท้ายด้วยสัญลักษณ์ [SHOW_MAP] เสมอ
3. หากข้อมูลใน [ข้อมูลอ้างอิง] ไม่มี ให้ตอบอย่างใจดีว่า 'พี่หาข้อมูลส่วนนี้ให้น้องไม่ได้จริงๆ ครับ ลองสอบถามคุณครูประชาสัมพันธ์ดูนะ' ห้ามเดาข้อมูลเอง
4. เรื่องคะแนนพฤติกรรม: ย้ำว่าโควตาต่อ ม.4 ต้องมีไม่ต่ำกว่า 60 คะแนน (10 ความดี = 1 พฤติกรรม)
5. ลงท้ายด้วย 'ครับ' ทุกประโยคเพื่อให้ดูสุภาพและเป็นทางการในแบบรุ่นพี่
6. การแสดงรูปภาพ: 
   - หากถามถึงแผนผัง ให้ตอบข้อความแล้วปิดท้ายด้วย [SHOW_MAP] เท่านั้น
   - หากมีรูปภาพจากฐานข้อมูล ให้ใช้รูปแบบ [SHOW_IMG:URL] 
   - ห้ามใส่ HTML หรือ Javascript มาในคำตอบเด็ดขาด

คำถามจากน้อง: {$userMessageSafe}
คำตอบจากพี่ RW-AI:";

$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ], 
    "generationConfig" => [
        "temperature" => 0.2, // พี่แนะนำ 0.2 เพื่อให้ AI ตอบนิ่งและสุภาพตามกฎ
        "maxOutputTokens" => 1024,
        "topP" => 0.8,
        "topK" => 10
    ]
    // หมายเหตุ: ตัด safetySettings ออกก่อนชั่วคราวเพื่อลดโอกาส Error 400
];

$success = false; // อย่าลืมประกาศตัวแปรนี้ไว้ก่อนเริ่ม foreach ด้วยครับ
$aiResponse = "";
$httpCode = 0;

foreach ($config['gemini']['models'] as $index => $currentModel) {
    if (!$currentModel) continue;

    $currentModel = trim($currentModel);
    // ลบ "models/" ออกไปก่อนเพื่อกันพลาด แล้วค่อยเติมกลับเข้าไปให้ถูก format
    $cleanModelName = str_replace('models/', '', $currentModel);
    
    // URL ที่ Google ยอมรับแน่นอนในเวอร์ชัน v1beta
    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModelName}:generateContent?key=" . urlencode((string)$config['gemini']['api_key']);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    // ตั้งเวลาให้พอเหมาะ (12 วินาทีต่อรุ่น)
    curl_setopt($ch, CURLOPT_TIMEOUT, 12); 
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $rawResponse = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $rawResponse !== false) {
        $resData = json_decode($rawResponse, true);
        if (isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
            $aiResponse = $resData['candidates'][0]['content']['parts'][0]['text'];
            $success = true;
            break; 
        }
    }

    // ถ้าเจอ 404 ให้ข้ามไปลองรุ่นถัดไปทันทีไม่ต้องรอ
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
