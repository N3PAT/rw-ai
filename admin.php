<?php
declare(strict_types=1);
session_start();

/**
 * RW-AI Admin Dashboard (Optimized for PHP 7.4)
 * Features: Database Capacity, System Status, Export CSV, Chat Logs
 */

// --- 1. การเชื่อมต่อฐานข้อมูลและโหลดค่า Environment ---
$envPath = __DIR__ . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) continue;
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
    die("เชื่อมต่อฐานข้อมูลล้มเหลว: " . mysqli_connect_error());
}
$conn->set_charset("utf8mb4");

function getUserIP(): string {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
}

$userIP = getUserIP();
$error_msg = "";

// --- 2. ฟังก์ชัน EXPORT CSV ---
if (isset($_GET['action']) && $_GET['action'] === 'export' && isset($_SESSION['admin_logged_in'])) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=chat_export_' . date('Ymd_His') . '.csv');
    // ใส่ BOM สำหรับรองรับภาษาไทยใน Excel
    echo "\xEF\xBB\xBF"; 
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'IP Address', 'User Message', 'AI Response', 'Timestamp']);
    
    $res = $conn->query("SELECT id, ip_address, user_message, ai_response, created_at FROM chat_logs ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit;
}

// --- 3. จัดการการเข้าสู่ระบบ/ออกจากระบบ ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password_hash FROM system_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header("Location: admin.php");
            exit;
        } else {
            $error_msg = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $error_msg = "ไม่พบชื่อผู้ใช้นี้";
    }
    $stmt->close();
}

// --- 4. ดึงข้อมูลสถิติและ LOGS ---
$logs = [];
$total_chats = 0;
$db_capacity_mb = "0.00";

if (isset($_SESSION['admin_logged_in'])) {
    // ดึง Log ล่าสุด
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 500");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
    }
    // นับจำนวนแชททั้งหมด
    $cRes = $conn->query("SELECT COUNT(*) as total FROM chat_logs");
    $total_chats = $cRes ? (int)$cRes->fetch_assoc()['total'] : 0;

    // คำนวณความจุฐานข้อมูล (DB Capacity)
    $size_stmt = $conn->prepare("SELECT SUM(data_length + index_length) AS size FROM information_schema.TABLES WHERE table_schema = ?");
    $size_stmt->bind_param("s", $db_name);
    $size_stmt->execute();
    $size_res = $size_stmt->get_result();
    if ($sRow = $size_res->fetch_assoc()) {
        $db_capacity_mb = number_format((float)$sRow['size'] / 1024 / 1024, 2);
    }
    $size_stmt->close();
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
        body { font-family: 'Kanit', sans-serif; background-color: #f1f5f9; }
        .log-container::-webkit-scrollbar { width: 4px; }
        .log-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body class="text-slate-800">

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <!-- หน้า Login -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="bg-white p-8 rounded-3xl shadow-2xl border border-slate-100 w-full max-w-md">
            <div class="text-center mb-8">
                <div class="w-20 h-20 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 shadow-lg">
                    <svg class="text-white w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002-2zm10-10V7a4 4 0 00-8 0v4h8z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                </div>
                <h1 class="text-3xl font-bold text-slate-900">RW-AI Admin</h1>
                <p class="text-slate-400 text-sm mt-2">กรุณาเข้าสู่ระบบเพื่อจัดการข้อมูล</p>
            </div>

            <?php if ($error_msg): ?>
                <div class="mb-4 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 text-sm font-medium rounded-r-lg">
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Username</label>
                    <input type="text" name="username" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all bg-slate-50 focus:bg-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Password</label>
                    <input type="password" name="password" required class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500 outline-none transition-all bg-slate-50 focus:bg-white">
                </div>
                <button type="submit" name="login" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-4 rounded-xl shadow-lg shadow-blue-200 transition-all transform active:scale-[0.98]">
                    เข้าสู่ระบบ
                </button>
            </form>
            <p class="text-center text-[10px] text-slate-300 mt-8 uppercase tracking-widest">Client IP: <?php echo $userIP; ?></p>
        </div>
    </div>
<?php else: ?>
    <!-- หน้า Dashboard -->
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50 no-print">
        <div class="max-w-7xl mx-auto px-6 h-20 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white font-black shadow-blue-100 shadow-lg italic">RW</div>
                <span class="font-bold text-xl text-slate-900 tracking-tight">Admin <span class="text-blue-600">Dashboard</span></span>
            </div>
            <div class="flex items-center gap-6">
                <div class="hidden md:flex flex-col items-end">
                    <span class="text-xs font-bold text-slate-400 uppercase tracking-tighter">ผู้ดูแลระบบ</span>
                    <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                </div>
                <a href="?action=logout" class="bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 px-5 py-2 rounded-xl text-sm font-bold transition-all border border-slate-200">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-10">
        
        <!-- แผงแสดงสถานะระบบ (System Status) -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-10 no-print">
            <!-- สถานะความจุ DB -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200 relative overflow-hidden group">
                <div class="absolute right-0 top-0 p-4 opacity-10 group-hover:scale-110 transition-transform">
                    <svg class="w-16 h-16" fill="currentColor" viewBox="0 0 20 20"><path d="M3 12v3c0 1.1.9 2 2 2h10a2 2 0 002-2v-3a2 2 0 00-2-2H5a2 2 0 00-2 2zm2-1a2 2 0 00-2 2v3a2 2 0 002 2h10a2 2 0 002-2v-3a2 2 0 00-2-2H5z"></path><path d="M3 7c0 1.1.9 2 2 2h10a2 2 0 002-2V4a2 2 0 00-2-2H5a2 2 0 00-2 2v3z"></path></svg>
                </div>
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">ความจุฐานข้อมูล</p>
                <h3 class="text-3xl font-black text-blue-600"><?php echo $db_capacity_mb; ?> <span class="text-sm font-normal text-slate-400">MB</span></h3>
                <p class="text-xs text-slate-400 mt-2">พื้นที่ที่ใช้จริงใน <?php echo htmlspecialchars($db_name); ?></p>
            </div>

            <!-- จำนวนการสนทนา -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">จำนวนแแชททั้งหมด</p>
                <h3 class="text-3xl font-black text-slate-900"><?php echo number_format($total_chats); ?></h3>
                <p class="text-xs text-slate-400 mt-2">รายการที่บันทึกสำเร็จ</p>
            </div>

            <!-- เวอร์ชันระบบ -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">PHP เวอร์ชัน</p>
                <h3 class="text-3xl font-black text-slate-900"><?php echo phpversion(); ?></h3>
                <p class="text-xs text-emerald-500 mt-2 font-bold flex items-center gap-1">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse"></span> ระบบทำงานปกติ
                </p>
            </div>

            <!-- ข้อมูล Host -->
            <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200">
                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Server Host</p>
                <h3 class="text-xl font-bold text-slate-900 truncate mt-2"><?php echo htmlspecialchars($db_host); ?></h3>
                <p class="text-xs text-slate-400 mt-2 font-mono">Port: <?php echo $db_port; ?></p>
            </div>
        </div>

        <!-- ส่วนหัวของตารางและปุ่ม Export -->
        <div class="flex flex-col md:flex-row justify-between items-end md:items-center mb-6 gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900">บันทึกการสนทนา (Chat Logs)</h2>
                <p class="text-sm text-slate-500">แสดงผลล่าสุด 500 รายการ</p>
            </div>
            <a href="?action=export" class="bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold px-8 py-3.5 rounded-2xl flex items-center gap-2 transition-all shadow-lg shadow-emerald-100 hover:shadow-emerald-200 transform active:scale-95 no-print">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                ส่งออกเป็นไฟล์ CSV
            </a>
        </div>

        <!-- ตารางข้อมูล Chat Log -->
        <div class="bg-white rounded-[2rem] shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-100">
                            <th class="px-6 py-5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">วัน-เวลา</th>
                            <th class="px-6 py-5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">Network / IP</th>
                            <th class="px-6 py-5 text-[10px] font-bold text-slate-400 uppercase tracking-widest">การสนทนา</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="3" class="px-6 py-20 text-center text-slate-400 italic">ไม่พบข้อมูลการสนทนาในขณะนี้</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-blue-50/30 transition-colors">
                                    <td class="px-6 py-6 whitespace-nowrap">
                                        <div class="text-xs font-bold text-slate-900"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                        <div class="text-[10px] text-slate-400"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-6 whitespace-nowrap">
                                        <span class="text-[10px] font-mono bg-slate-100 text-slate-600 px-2 py-1 rounded-lg border border-slate-200"><?php echo htmlspecialchars($log['ip_address']); ?></span>
                                    </td>
                                    <td class="px-6 py-6 min-w-[350px]">
                                        <div class="flex flex-col gap-4">
                                            <!-- ส่วนของ User -->
                                            <div class="flex gap-3 items-start">
                                                <div class="w-6 h-6 bg-blue-100 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                                                    <svg class="w-3.5 h-3.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20"><path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"></path></svg>
                                                </div>
                                                <p class="text-xs text-slate-700 leading-relaxed"><?php echo htmlspecialchars($log['user_message']); ?></p>
                                            </div>
                                            <!-- ส่วนของ AI -->
                                            <div class="flex gap-3 items-start bg-slate-50 p-4 rounded-2xl border border-slate-100">
                                                <div class="w-6 h-6 bg-emerald-100 rounded-lg flex items-center justify-center shrink-0 mt-0.5">
                                                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path d="M11 3a1 1 0 10-2 0v1a1 1 0 102 0V3zM5.884 6.607a1 1 0 011.414 0l.707.707a1 1 0 11-1.414 1.414l-.707-.707a1 1 0 010-1.414zm2.121 2.121a1 1 0 01-1.414 0l-.707-.707a1 1 0 011.414-1.414l.707.707a1 1 0 010 1.414zM15 11a1 1 0 100-2H14a1 1 0 100 2h1zM5 11a1 1 0 100-2H4a1 1 0 100 2h1zM14.116 6.607a1 1 0 00-1.414 0l-.707.707a1 1 0 101.414 1.414l.707-.707a1 1 0 000-1.414zM3.184 12.035a1 1 0 010 1.414l-.707.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM9 15a1 1 0 100 2v1a1 1 0 102 0v-1a1 1 0 10-2 0z"></path></svg>
                                                </div>
                                                <div class="text-xs text-slate-500 leading-relaxed italic log-container max-h-40 overflow-y-auto pr-2">
                                                    <?php echo nl2br(htmlspecialchars($log['ai_response'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-10 pt-8 border-t border-slate-200 flex flex-col md:flex-row justify-between items-center gap-4 text-slate-400">
            <p class="text-[10px] font-bold uppercase tracking-[0.3em]">System Engine v2.5</p>
            <p class="text-[10px]">&copy; <?php echo date('Y'); ?> RW-AI Managed Services. All rights reserved.</p>
        </div>
    </main>
<?php endif; ?>

</body>
</html>
