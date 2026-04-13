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
// วิธีใช้: ส่งค่าผ่าน URL เช่น admin.php?setup_user=super_admin&setup_pass=RW-Admin!@#2026_Secure
if (isset($_GET['setup_user']) && isset($_GET['setup_pass'])) {
    $sUser = trim($_GET['setup_user']);
    $sPass = $_GET['setup_pass'];
    $sHash = password_hash($sPass, PASSWORD_BCRYPT);
    
    // ตรวจสอบโครงสร้างตารางก่อนเผื่อพัง
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
        // ใช้ password_verify ตามมาตรฐานความปลอดภัยสูงสุด
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
    header('Content-Disposition: attachment; filename=chat_logs_' . date('Y-m-d_H-i-s') . '.csv');
    // เพิ่ม BOM (Byte Order Mark) เพื่อให้ Excel เปิดภาษาไทยได้ถูกต้อง
    echo "\xEF\xBB\xBF"; 
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'IP Address', 'User Message', 'AI Response', 'Timestamp'));
    
    // ดึงข้อมูลทั้งหมดโดยไม่จำกัด 500 รายการสำหรับการ Export
    $res = $conn->query("SELECT id, ip_address, user_message, ai_response, created_at FROM chat_logs ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// --- 4. FETCH DATA & SYSTEM STATS ---
$logs = [];
$total_logs = 0;
$db_size_mb = 0.00;

if (isset($_SESSION['admin_logged_in'])) {
    // 1. ดึง Log 500 รายการล่าสุด
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 500");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    // 2. นับจำนวน Log ทั้งหมด
    $count_res = $conn->query("SELECT COUNT(*) as total FROM chat_logs");
    if ($count_res) {
        $total_logs = $count_res->fetch_assoc()['total'];
    }

    // 3. คำนวณขนาดความจุของ Database (เฉพาะ Database ปัจจุบัน)
    $size_stmt = $conn->prepare("
        SELECT SUM(data_length + index_length) AS size 
        FROM information_schema.TABLES 
        WHERE table_schema = ?
    ");
    if ($size_stmt) {
        $size_stmt->bind_param("s", $db_name);
        $size_stmt->execute();
        $size_res = $size_stmt->get_result();
        if ($size_row = $size_res->fetch_assoc()) {
            // แปลง Byte เป็น Megabyte
            $db_size_mb = round((float)$size_row['size'] / 1024 / 1024, 2);
        }
        $size_stmt->close();
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
        /* สไตล์สำหรับ Scrollbar ใน Table */
        .table-container::-webkit-scrollbar { height: 8px; width: 8px; }
        .table-container::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .table-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .table-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="text-gray-800">

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-2xl shadow-xl border border-gray-100 w-full max-w-md relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1 bg-blue-600"></div>
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-800">RW-AI Admin</h1>
                <p class="text-gray-500 text-sm mt-1">ระบบจัดการหลังบ้าน</p>
                <div class="mt-3 inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-xs text-gray-500">
                    IP: <?php echo htmlspecialchars($userIP); ?>
                </div>
            </div>

            <?php if ($success_msg): ?>
                <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6 text-sm flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                    <?php echo $success_msg; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
                <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6 text-sm flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path></svg>
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="admin.php">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-200">
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>
    </div>
<?php else: ?>
    <!-- Navbar -->
    <div class="no-print bg-white shadow-sm border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold">
                        RW
                    </div>
                    <h1 class="text-xl font-bold text-gray-800 tracking-tight">Admin Dashboard</h1>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-sm text-gray-500 hidden sm:block">
                        ยินดีต้อนรับ, <span class="font-semibold text-gray-700"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                    </div>
                    <a href="?action=logout" class="px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-md text-sm font-medium transition-colors border border-red-100">
                        ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- System Status Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8 no-print">
            
            <!-- Card 1: Total Logs -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="p-3 bg-blue-50 text-blue-600 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">จำนวนแชททั้งหมด</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo number_format($total_logs); ?> <span class="text-sm font-normal text-gray-400">รายการ</span></div>
                </div>
            </div>

            <!-- Card 2: DB Capacity -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="p-3 bg-purple-50 text-purple-600 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">ขนาดฐานข้อมูล</div>
                    <div class="text-2xl font-bold text-gray-900"><?php echo $db_size_mb; ?> <span class="text-sm font-normal text-gray-400">MB</span></div>
                </div>
            </div>

            <!-- Card 3: DB Version -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="p-3 bg-orange-50 text-orange-600 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 22h14a2 2 0 0 0 2-2V7.5L14.5 2H6a2 2 0 0 0-2 2v4"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M2 15h10"></path><path d="M9 18v-6"></path></svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">เวอร์ชัน Database</div>
                    <div class="text-base font-semibold text-gray-800 mt-1 truncate w-32" title="<?php echo htmlspecialchars($conn->server_info); ?>">
                        <?php echo htmlspecialchars($conn->server_info); ?>
                    </div>
                </div>
            </div>

            <!-- Card 4: Server Status -->
            <div class="bg-white p-5 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
                <div class="p-3 bg-green-50 text-green-600 rounded-xl">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line></svg>
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-500">สถานะเซิร์ฟเวอร์</div>
                    <div class="flex items-center mt-1">
                        <span class="relative flex h-3 w-3 mr-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                        </span>
                        <span class="text-sm font-bold text-gray-800">Online <span class="text-xs font-normal text-gray-500">(PHP <?php echo phpversion(); ?>)</span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Header & Actions -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">ประวัติการสนทนา</h2>
                <p class="text-sm text-gray-500">แสดงข้อมูล 500 รายการล่าสุด (กด Export เพื่อโหลดข้อมูลทั้งหมด)</p>
            </div>
            
            <a href="?action=export" class="no-print inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-600 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-sm hover:shadow-md text-sm active:scale-95">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Export เป็น CSV
            </a>
        </div>

        <!-- Data Table -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto table-container max-h-[60vh]">
                <table class="min-w-full divide-y divide-gray-200 relative">
                    <thead class="bg-gray-50 sticky top-0 z-10 shadow-sm">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">เวลา (Timestamp)</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider whitespace-nowrap">IP Address</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[250px]">ข้อความจากผู้ใช้ (User)</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider min-w-[300px]">คำตอบจากระบบ (AI)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">ไม่พบประวัติการสนทนาในระบบ</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">
                                    <?php echo $log['ip_address']; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-800 break-words">
                                    <?php echo nl2br(htmlspecialchars($log['user_message'])); ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600 break-words">
                                    <div class="max-h-32 overflow-y-auto pr-2 custom-scrollbar">
                                        <?php echo nl2br(htmlspecialchars($log['ai_response'])); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    <style>
        .custom-scrollbar::-webkit-scrollbar { width: 4px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
<?php endif; ?>
</body>
</html>
