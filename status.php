<?php
// --- 1. SETTINGS & ENV ---
$envPath = __DIR__ . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
            list($name, $value) = explode('=', $line, 2);
            putenv(trim($name) . "=" . trim($value));
        }
    }
} else {
    // ถ้าอ่าน .env ไม่ได้ ให้แจ้งเตือนเบาๆ ใน Log หรือข้ามไป (ระบบอาจจะใช้ Environment Variables ของระบบแทน)
}


// ฟังก์ชันสร้างแท่งสัญญาณ
function getStatusBars($status) {
    $isOnline = ($status === 'ONLINE');
    $colorClass = $isOnline ? 'bg-green-500' : 'bg-red-500';
    $opacityClass = $isOnline ? '' : 'opacity-50'; // ถ้าล่มให้จางนิดนึงหรือจะแดงเข้มก็ได้

    return '
    <div class="flex items-end gap-1 h-6" title="' . $status . '">
        <div class="w-1.5 h-2.5 rounded-full ' . $colorClass . '"></div>
        <div class="w-1.5 h-4 rounded-full ' . $colorClass . '"></div>
        <div class="w-1.5 h-6 rounded-full ' . $colorClass . '"></div>
    </div>';
}

function checkDatabase() {
    $host = getenv('DB_HOST');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    $name = getenv('DB_NAME');
    $port = getenv('DB_PORT') ?: 3306;

    $start = microtime(true);
    $conn = @new mysqli($host, $user, $pass, $name, (int)$port);
    $end = microtime(true);

    if ($conn->connect_error) {
        return ['status' => 'DOWN', 'msg' => $conn->connect_error, 'latency' => '-'];
    }
    $conn->close();
    return ['status' => 'ONLINE', 'msg' => 'Connected successfully', 'latency' => round(($end - $start) * 1000, 2) . ' ms'];
}

function checkGemini() {
    $apiKey = getenv('GEMINI_API_KEY');
    $model = getenv('GEMINI_MODEL') ?: 'gemini-pro';
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}?key={$apiKey}";

    $start = microtime(true);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $end = microtime(true);

    if ($httpCode === 200) {
        return ['status' => 'ONLINE', 'msg' => 'API Key is valid', 'latency' => round(($end - $start) * 1000, 2) . ' ms'];
    } else {
        return ['status' => 'DOWN', 'msg' => "Error Code: $httpCode", 'latency' => '-'];
    }
}

$dbStatus = checkDatabase();
$geminiStatus = checkGemini();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI System Status</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .status-card { transition: all 0.3s ease; }
        .status-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-gray-50 p-4 md:p-8">
    <div class="max-w-xl mx-auto">
        <header class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-gray-800">System Status</h1>
            <p class="text-gray-500 mt-2">ตรวจสอบสถานะการเชื่อมต่อแบบเรียลไทม์</p>
        </header>

        <div class="space-y-4">
            <div class="status-card bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800">Host Server</h3>
                    <p class="text-xs text-gray-400 mt-1 uppercase tracking-wider">PHP Environment</p>
                    <p class="text-sm text-gray-500 mt-1">Version: <?php echo phpversion(); ?></p>
                </div>
                <?php echo getStatusBars('ONLINE'); ?>
            </div>

            <div class="status-card bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800">Database (MySQL)</h3>
                    <p class="text-xs text-gray-400 mt-1 uppercase tracking-wider">Connection Status</p>
                    <p class="text-sm text-gray-500 mt-1">Latency: <span class="text-blue-600 font-medium"><?php echo $dbStatus['latency']; ?></span></p>
                </div>
                <?php echo getStatusBars($dbStatus['status']); ?>
            </div>

            <div class="status-card bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-gray-800">Google Gemini API</h3>
                    <p class="text-xs text-gray-400 mt-1 uppercase tracking-wider">Intelligence Core</p>
                    <p class="text-sm text-gray-500 mt-1">Latency: <span class="text-blue-600 font-medium"><?php echo $geminiStatus['latency']; ?></span></p>
                </div>
                <?php echo getStatusBars($geminiStatus['status']); ?>
            </div>
        </div>

        <?php if($dbStatus['status'] === 'DOWN' || $geminiStatus['status'] === 'DOWN'): ?>
            <div class="mt-6 p-4 bg-red-50 border border-red-100 rounded-xl">
                <p class="text-sm text-red-600 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    ตรวจพบข้อผิดพลาด: <?php echo ($dbStatus['status'] === 'DOWN') ? 'DB: '.$dbStatus['msg'] : 'API: '.$geminiStatus['msg']; ?>
                </p>
            </div>
        <?php endif; ?>

        <footer class="mt-10 text-center">
            <button onclick="location.reload()" class="bg-blue-600 text-white px-8 py-3 rounded-2xl hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-200 font-bold text-sm">
                REFRESH STATUS
            </button>
            <p class="text-[10px] text-gray-400 mt-6 font-medium">LAST CHECKED: <?php echo date('H:i:s d/m/Y'); ?></p>
        </footer>
    </div>
</body>
</html>
