<?php
declare(strict_types=1);

// --- 0. LOAD ENV (Simple Native Loader - Improved) ---
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

// เพิ่ม Port เข้าไปใน Config เพื่อรองรับ Aiven (ค่าเริ่มต้น 14495)
$config = [
    "db" => [
        "host" => getenv('DB_HOST') ?: "mysql-1a75280e-contactmenatthapat-d615.b.aivencloud.com",
        "user" => getenv('DB_USER') ?: "avnadmin",
        "pass" => getenv('DB_PASS') ?: "AVNS_VEtjZDbOnsglAYCRj8U",
        "name" => getenv('DB_NAME') ?: "defaultdb",
        "port" => (int)(getenv('DB_PORT') ?: 14495) 
    ],
    "gemini" => [
        "api_key" => getenv('GEMINI_API_KEY') ?: "AIzaSyD-LdpIKRTgxCgIQwhs9j4IfrVbE6_ruYQ",
        "model"   => getenv('GEMINI_MODEL') ?: "gemini-1.5-flash"
    ]
];

function send_json(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- 2. DATABASE CONNECTION (ปรับปรุงเพื่อรองรับ SSL ของ Aiven) ---
$conn = mysqli_init();
// ข้ามการตรวจสอบใบเซอร์เพื่อความรวดเร็วในการ Test (แต่ยังใช้ SSL ในการเข้ารหัสข้อมูล)
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 

$success = $conn->real_connect(
    $config['db']['host'], 
    $config['db']['user'], 
    $config['db']['pass'], 
    $config['db']['name'], 
    $config['db']['port'], 
    NULL, 
    MYSQLI_CLIENT_SSL // บังคับใช้ SSL
);

if (!$success) {
    send_json(["response" => "ระบบฐานข้อมูลขัดข้องชั่วคราวครับ พี่กำลังรีบซ่อมอยู่! (" . mysqli_connect_error() . ")"]);
}
$conn->set_charset("utf8mb4");

// --- 3. INPUT PROCESSING ---
$input = json_decode(file_get_contents('php://input'), true);
$userMessageRaw = trim((string)($input['message'] ?? ''));
$userMessageSafe = htmlspecialchars(mb_substr($userMessageRaw, 0, 500, 'UTF-8'), ENT_QUOTES, 'UTF-8');

if ($userMessageSafe === '') {
    send_json(["response" => "พิมพ์คำถามมาได้เลยครับ พี่ RW-AI รออยู่!"]);
}

// --- 4. SMART LOAD ENGINE (Context Preparation) ---
$context = [
    "info" => "", 
    "admins" => "", 
    "behavior" => "", 
    "rules" => "", 
    "buildings" => "", 
    "history" => "", 
    "curriculum" => "", 
    "subjects" => "",
    "map_url" => ""
];

// --- 4.1 ข้อมูลพื้นฐาน ---
$resProfile = $conn->query("SELECT info_key, info_value_th, category FROM school_profile");
if ($resProfile) {
    while ($row = $resProfile->fetch_assoc()) {
        $cat = $row['category'];
        
        if ($row['info_key'] === 'แผนผัง') {
            $context['map_url'] = $row['info_value_th'];
        } else {
            // เช็ค Keyword สำหรับหมวดเฉพาะทาง (เพิ่มตัวแปรที่ขาดหายไป)
            $isSong = preg_match('/(เพลง|มาร์ช|ร้องเพลง|ทำนอง)/u', $userMessageRaw);
            $isHistory = preg_match('/(ประวัติ|ก่อตั้ง|ปีที่|หลวงเทวฤทธิ์)/u', $userMessageRaw);
            $isUniform = preg_match('/(แต่งกาย|ชุดนักเรียน|ผมยาว)/u', $userMessageRaw); 
            $isFinance = preg_match('/(ค่าเทอม|จ่ายเงิน|การเงิน)/u', $userMessageRaw); 
            
            // เงื่อนไขการเลือกข้อมูลเข้า Context
            if (
                $cat === 'general' || 
                $cat === 'identity' || 
                $cat === 'philosophy' || 
                ($isUniform && $cat === 'rules') || 
                ($isFinance && $cat === 'finance') ||
                ($isSong && $cat === 'song') ||
                ($isSong && $cat === 'identity') || 
                ($isHistory && $cat === 'history') ||
                $cat === 'information' 
            ) {
                $context['info'] .= "- {$row['info_key']}: {$row['info_value_th']}\n";
            }
        }
    }
}

// 4.2 ข้อมูลผู้บริหารและเกณฑ์พฤติกรรม
$resAdmins = $conn->query("SELECT name, position FROM school_administrators LIMIT 10");
if ($resAdmins) {
    while ($row = $resAdmins->fetch_assoc()) {
        $context['admins'] .= "- {$row['name']} ({$row['position']})\n";
    }
}

$resBehavior = $conn->query("SELECT condition_name, min_score, effect FROM behavior_thresholds");
if ($resBehavior) {
    while ($row = $resBehavior->fetch_assoc()) {
        $context['behavior'] .= "- {$row['condition_name']}: {$row['min_score']} คะแนน -> {$row['effect']}\n";
    }
}

// 4.3 ระบบ Keyword Matching
$keywords = [
    'buildings'  => '/(ตึก|อาคาร|ห้อง|เรียนที่ไหน|แผนผัง|สหกรณ์|ซื้อของ|ขายของ|ที่ตั้ง)/u',
    'rules'      => '/(กฎ|ระเบียบ|ผิด|โดน|โทษ|ผมยาว|คะแนน|หัก|ทัณฑ์บน|ตัดคะแนน|ระเบียบ)/u',
    'history'    => '/(ประวัติ|ก่อตั้ง|ปีที่ตั้ง|ผอ.คนแรก|เรื่องราว)/u',
    'curriculum' => '/(แผน|สายการเรียน|ห้องเรียน|ม.ต้น|ม.ปลาย|กิ๊ฟ|วิทย์|ศิลป์|ห้อง)/u'
];

if (preg_match($keywords['buildings'], $userMessageRaw)) {
    $res = $conn->query("SELECT building_name, floor, room_info, image_url FROM school_buildings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $context['buildings'] .= "อาคาร:{$row['building_name']} ชั้น:{$row['floor']} ({$row['room_info']}) [Image:{$row['image_url']}]\n";
        }
    }
}

if (preg_match($keywords['rules'], $userMessageRaw)) {
    $res = $conn->query("SELECT category, description, punishment FROM school_rules");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $context['rules'] .= "หมวด:{$row['category']} - {$row['description']} (บทลงโทษ: {$row['punishment']})\n";
        }
    }
}

if (preg_match($keywords['history'], $userMessageRaw)) {
    $res = $conn->query("SELECT topic, detail FROM school_history");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $context['history'] .= "- {$row['topic']}: {$row['detail']}\n";
        }
    }
}

if (preg_match($keywords['curriculum'], $userMessageRaw)) {
    $res = $conn->query("SELECT level, room_type, room_number, program_name FROM curriculum_rooms");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $context['curriculum'] .= "{$row['level']} ห้อง {$row['room_number']} ({$row['room_type']}): {$row['program_name']}\n";
        }
    }
}

// --- 5. PROMPT CONSTRUCTION ---
$prompt = "คุณคือ 'พี่ RW-AI' รุ่นพี่อัจฉริยะของโรงเรียนฤทธิยะวรรณาลัย 
ตอบน้องอย่างสุภาพ เป็นกันเอง มีหางเสียง 'ครับ' ทุกประโยค

[ข้อมูลโรงเรียน]
{$context['info']}
{$context['admins']}
{$context['behavior']}
{$context['rules']}
{$context['buildings']}
{$context['history']}
{$context['curriculum']}

[กฎเหล็กในการตอบ]
1. เรื่องต่อโควตา ม.4: ต้องย้ำว่าคะแนนพฤติกรรมสะสมห้ามต่ำกว่า 60 คะแนน
2. การคำนวณคะแนน: 10 คะแนนความดี = 1 คะแนนพฤติกรรม
3. แผนการเรียน: ระบุเลขห้องให้ชัดเจนเสมอ
4. การแสดงรูปภาพ: 
   - ถ้าถามทาง/แผนผัง: <br><img src='{$context['map_url']}' class='w-full rounded-lg shadow-md my-2' alt='แผนผัง'>
   - ถ้าถามสถานที่เฉพาะ: ใช้ <br><img src='URL' class='w-full rounded-lg shadow-md my-2'> จากข้อมูลที่ให้ไป
5. ความซื่อสัตย์: ถ้าไม่มีข้อมูลในนี้ ให้บอกว่า 'พี่ขอโทษครับ พี่ยังไม่มีข้อมูลส่วนนี้ ลองถามที่ห้องธุรการดูนะครับ' ห้ามเดา
6. รหัสวิชา: วิเคราะห์ตัวหน้า ท=ไทย, ค=คณิต, ว=วิทย์, ส=สังคม, พ=พละ, ศ=ศิลปะ, ง=การงาน, อ=อังกฤษ, จ=จีน, ก=กิจกรรม, I=IS
7. ระเบียบห้องเรียน: ตึกปิด 16.30 น., ขอใช้ห้องล่วงหน้า 3 วัน, ห้ามกินขนมในห้อง, ปิดไฟปิดแอร์เสมอ
8. ผู้ปกครอง: ทำทัณฑ์บนต้องมาเองเท่านั้น ห้ามฝากคนอื่น

คำถามจากน้อง: {$userMessageSafe}
คำตอบพี่ RW-AI:";

// --- 6. AI API CALL (No cURL, using file_get_contents) ---
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$config['gemini']['model']}:generateContent?key=" . urlencode($config['gemini']['api_key']);

$payload = ["contents" => [["parts" => [["text" => $prompt]]]], "generationConfig" => ["temperature" => 0.1, "maxOutputTokens" => 1024]];

$success = false;
$retryCount = 0;
$aiResponse = "";
$httpCode = 0;

while (!$success && $retryCount < 2) {
    // สร้าง Stream Context สำหรับยิง POST Request
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($payload),
            'timeout' => 20,
            'ignore_errors' => true 
        ],
        'ssl' => [
            'verify_peer'      => false,
            'verify_peer_name' => false,
        ]
    ];
    
    $contextStream = stream_context_create($options);
    
    // ยิง Request
    $rawResponse = @file_get_contents($apiUrl, false, $contextStream);
    
    // ดึง HTTP Status Code 
    if (isset($http_response_header) && is_array($http_response_header)) {
        if (preg_match('#HTTP/\d+\.\d+ (\d+)#', $http_response_header[0], $matches)) {
            $httpCode = intval($matches[1]);
        }
    }

    if ($httpCode === 200 && $rawResponse !== false) {
        $resData = json_decode($rawResponse, true);
        $aiResponse = $resData['candidates'][0]['content']['parts'][0]['text'] ?? "";
        $success = true;
    } else {
        $retryCount++;
        usleep(500000); // รอ 0.5 วินาที
    }
}

// --- 7. FINAL OUTPUT ---
if ($success && !empty($aiResponse)) {
    send_json(["response" => trim($aiResponse)]);
} else {
    send_json(["response" => "พี่ RW-AI ขออภัยครับ ระบบประมวลผลขัดข้องชั่วคราว ลองถามใหม่อีกครั้งนะครับ (Error Code: $httpCode)"]);
}
