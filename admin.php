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
$db_port = (int)getenv('DB_PORT');

$conn = mysqli_init();
mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); 
$success = @$conn->real_connect($db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);

if (!$success) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
$conn->set_charset("utf8mb4");

// ฟังก์ชันดึง IP
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

// --- 2. AUTO-SETUP ADMIN (ถ้ายังไม่มีแอดมินในระบบเลย) ---
$checkAdmin = $conn->query("SELECT COUNT(*) as c FROM system_admins");
$adminCount = $checkAdmin->fetch_assoc()['c'] ?? 0;
if ($adminCount == 0) {
    $defaultUser = '';
    $defaultPass = '';
    $hashedPass = password_hash($defaultPass, PASSWORD_BCRYPT);
    $conn->query("INSERT INTO system_admins (username, password_hash) VALUES ('$defaultUser', '$hashedPass')");
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
        // ตรวจสอบรหัสผ่าน
        if (password_verify($password, $row['password_hash'])) {
            // ตรวจสอบ IP หากมีการตั้งค่า allowed_ip ไว้ (ความปลอดภัยขั้นสูง)
            if (!empty($row['allowed_ip']) && $row['allowed_ip'] !== $userIP) {
                $error_msg = "IP Address ของคุณไม่อนุญาตให้เข้าสู่ระบบ (IP: $userIP)";
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                header("Location: admin.php");
                exit;
            }
        } else {
            $error_msg = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error_msg = "ไม่พบชื่อผู้ใช้นี้";
    }
    $stmt->close();
}

// --- EXPORT TO CSV LOGIC ---
if (isset($_GET['action']) && $_GET['action'] == 'export' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=chat_logs_' . date('Y-m-d') . '.csv');
    // เพื่อให้เปิดใน Excel ภาษาไทยได้ไม่เพี้ยน ต้องใส่ BOM
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

// --- 4. FETCH DATA FOR DASHBOARD ---
$logs = [];
if (isset($_SESSION['admin_logged_in'])) {
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 500"); // ดึง 500 รายการล่าสุด
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
        /* ซ่อนส่วนอื่นๆ เวลาสั่งปริ้นท์ ให้เห็นแค่ตาราง */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #fff; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; font-size: 12px; }
            .print-title { display: block !important; text-align: center; font-size: 18px; margin-bottom: 20px;}
        }
        .print-title { display: none; }
    </style>
</head>
<body class="text-gray-800">

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <!-- ================= LOGIN PAGE ================= -->
    <div class="min-h-screen flex items-center justify-center">
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-blue-600">RW-AI Admin</h1>
                <p class="text-gray-500 text-sm mt-1">ระบบจัดการหลังบ้าน</p>
                <p class="text-xs text-gray-400 mt-2">Your IP: <?php echo htmlspecialchars($userIP); ?></p>
            </div>

            <?php if ($error_msg): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 text-sm">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition duration-200">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>

<?php else: ?>
    <!-- ================= ADMIN DASHBOARD ================= -->
    <div class="no-print bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center">
                    <h1 class="text-xl font-bold text-blue-600">RW-AI Dashboard</h1>
                    <span class="ml-4 px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">Logged in as: <?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
                <div>
                    <a href="?action=logout" class="text-sm text-red-600 hover:text-red-800 font-medium">ออกจากระบบ</a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <div class="print-title font-bold">รายงานบันทึกการใช้งานระบบ RW-AI</div>

        <div class="no-print flex flex-col md:flex-row justify-between items-center mb-6">
            <h2 class="text-2xl font-bold">ประวัติการสนทนา (Chat Logs)</h2>
            <div class="flex space-x-3 mt-4 md:mt-0">
                <button onclick="window.print()" class="bg-gray-800 hover:bg-gray-900 text-white px-4 py-2 rounded-lg text-sm shadow-sm transition">
                    พิมพ์ (Print / PDF)
                </button>
                <a href="?action=export" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm shadow-sm transition">
                    ส่งออกไฟล์ Excel (CSV)
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-20">ID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">เวลา</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-32">IP Address</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/3">ข้อความจากผู้ใช้</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">คำตอบจาก AI</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars((string)$log['id']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 font-mono"><?php echo htmlspecialchars((string)$log['ip_address']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-900 break-words max-w-xs"><?php echo htmlspecialchars((string)$log['user_message']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 break-words max-w-sm"><?php echo htmlspecialchars((string)$log['ai_response']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-gray-500">ยังไม่มีข้อมูลการสนทนาในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <p class="no-print text-xs text-gray-400 mt-4 text-right">แสดงข้อมูลสูงสุด 500 รายการล่าสุด (ระบบจะลบข้อมูลที่เก่าเกิน 20 วันอัตโนมัติ)</p>
    </div>
<?php endif; ?>

</body>
</html>
