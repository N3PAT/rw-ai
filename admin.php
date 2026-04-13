<?php
declare(strict_types=1);
session_start();

// --- 1. LOAD ENV & DB CONNECTION ---
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

$db_host = getenv('DB_HOST');
$db_user = getenv('DB_USER');
$db_pass = getenv('DB_PASS');
$db_name = getenv('DB_NAME');
$db_port = (int)getenv('DB_PORT') ?: 3306;

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 
$success = @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
$conn->set_charset("utf8mb4");

function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

$userIP = getUserIP();
$error_msg = "";
$success_msg = "";

// --- 2. TEMPORARY SETUP MODE (ใช้เพื่อแก้ปัญหาการเข้าไม่ได้) ---
if (isset($_GET['setup_user']) && isset($_GET['setup_pass'])) {
    $sUser = trim($_GET['setup_user']);
    $sPass = $_GET['setup_pass'];
    $sHash = password_hash($sPass, PASSWORD_BCRYPT);
    
    $conn->query("ALTER TABLE `system_admins` MODIFY `password_hash` VARCHAR(255) NOT NULL");
    
    $stmt = $conn->prepare("INSERT INTO system_admins (username, password_hash) VALUES (?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
    $stmt->bind_param("sss", $sUser, $sHash, $sHash);
    if ($stmt->execute()) {
        $success_msg = "อัปเดตรหัสผ่านสำหรับ User: $sUser เรียบร้อยแล้ว! กรุณาลบพารามิเตอร์บน URL ออกแล้วลอง Login";
    }
    $stmt->close();
}

// --- 3. HANDLE ACTIONS (Login, Logout, Export) ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password_hash, allowed_ip FROM system_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            if (!empty($row['allowed_ip']) && $row['allowed_ip'] !== $userIP) {
                $error_msg = "IP Address ของคุณไม่อนุญาตให้เข้าสู่ระบบ (IP: $userIP)";
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                header("Location: admin.php");
                exit;
            }
        } else {
            $error_msg = "รหัสผ่านไม่ถูกต้อง (Hash Mismatch)";
        }
    } else {
        $error_msg = "ไม่พบชื่อผู้ใช้: " . htmlspecialchars($username);
    }
    $stmt->close();
}

// --- EXPORT TO CSV LOGIC ---
if (isset($_GET['action']) && $_GET['action'] == 'export' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=chat_logs_' . date('Y-m-d') . '.csv');
    echo "\xEF\xBB\xBF"; 
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'IP Address', 'User Message', 'AI Response', 'Timestamp'));
    $res = $conn->query("SELECT id, ip_address, user_message, ai_response, created_at FROM chat_logs ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- 4. FETCH DATA & STATS ---
$logs = [];
$db_size_mb = 0;
$total_api_calls = 0;
$today_api_calls = 0;

if (isset($_SESSION['admin_logged_in'])) {
    // 1. หาความจุของ Database ปัจจุบัน (MB)
    $stmt_size = $conn->prepare("SELECT SUM(data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ?");
    $stmt_size->bind_param("s", $db_name);
    $stmt_size->execute();
    $res_size = $stmt_size->get_result();
    if ($row = $res_size->fetch_assoc()) {
        $db_size_mb = round($row['size'] / 1024 / 1024, 2);
    }
    $stmt_size->close();

    // 2. หาจำนวนการใช้งาน API (นับจาก Log)
    $res_total = $conn->query("SELECT COUNT(*) AS total FROM chat_logs");
    if ($res_total && $row = $res_total->fetch_assoc()) {
        $total_api_calls = $row['total'];
    }

    $res_today = $conn->query("SELECT COUNT(*) AS today FROM chat_logs WHERE DATE(created_at) = CURDATE()");
    if ($res_today && $row = $res_today->fetch_assoc()) {
        $today_api_calls = $row['today'];
    }

    // 3. ดึงข้อมูล Log สำหรับตาราง
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 500");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f3f4f6; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="text-gray-800">

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-blue-600">RW-AI Admin</h1>
                <p class="text-gray-500 text-sm mt-1">ระบบจัดการหลังบ้าน</p>
                <p class="text-xs text-gray-400 mt-2">IP: <?php echo htmlspecialchars($userIP); ?></p>
            </div>

            <?php if ($success_msg): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 text-sm">
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 text-sm">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Topbar -->
    <div class="no-print bg-white shadow-sm border-b p-4">
        <div class="max-w-7xl mx-auto flex justify-between items-center">
            <h1 class="text-xl font-bold text-blue-600">RW-AI Dashboard</h1>
            <div class="flex items-center gap-4">
                <a href="?action=export" class="text-green-600 hover:text-green-700 text-sm font-medium">📥 Export CSV</a>
                <a href="?action=logout" class="text-red-600 hover:text-red-700 text-sm font-medium">ออกจากระบบ</a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto p-4 sm:p-8">
        
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- DB Size Card -->
            <div class="bg-white rounded-xl shadow-sm border p-6 flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">Database Storage</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($db_size_mb, 2); ?> MB</p>
                </div>
            </div>

            <!-- API Usage Card -->
            <div class="bg-white rounded-xl shadow-sm border p-6 flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">API Usage (Total)</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo number_format($total_api_calls); ?> <span class="text-sm font-normal text-gray-500">requests</span></p>
                    <p class="text-xs text-gray-400 mt-1">วันนี้: +<?php echo number_format($today_api_calls); ?></p>
                </div>
            </div>

            <!-- System Status Card -->
            <div class="bg-white rounded-xl shadow-sm border p-6 flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500 font-medium">System Status</p>
                    <div class="flex items-center mt-1">
                        <span class="flex w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                        <p class="text-xl font-bold text-gray-800">Online</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Logs Table -->
        <h2 class="text-xl font-bold mb-4 text-gray-700">Chat Logs (500 รายการล่าสุด)</h2>
        <div class="bg-white rounded-xl shadow-sm overflow-x-auto border">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">เวลา</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/3">User Message</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-1/3">AI Response</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="4" class="px-6 py-4 text-center text-gray-500">ไม่มีข้อมูล Log</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $log['created_at']; ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-medium"><?php echo $log['ip_address']; ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900 break-words"><?php echo htmlspecialchars($log['user_message']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600 break-words"><?php echo htmlspecialchars($log['ai_response']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
</body>
</html>
