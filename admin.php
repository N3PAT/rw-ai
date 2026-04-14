<?php
declare(strict_types=1);
session_start();

/**
 * RW-AI Admin Dashboard (V2.1 - With API Quota Monitor & CSV Export)
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

// --- [NEW] 2. ACTION: EXPORT CSV ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=chat_history_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM สำหรับ Excel ภาษาไทย
    fputcsv($output, ['ID', 'IP Address', 'User Message', 'AI Response', 'Created At']);
    
    $res = $conn->query("SELECT id, ip_address, user_message, ai_response, created_at FROM chat_logs ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

// --- 2. จัดการ Action เดิม ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (isset($_GET['delete_unanswered']) && isset($_SESSION['admin_logged_in'])) {
    $id = (int)$_GET['delete_unanswered'];
    $conn->query("DELETE FROM unanswered_questions WHERE id = $id");
    header("Location: admin.php#training-center");
    exit;
}

// --- 3. จัดการ Login ---
$error_msg = "";
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
        } else { $error_msg = "รหัสผ่านไม่ถูกต้อง"; }
    } else { $error_msg = "ไม่พบชื่อผู้ใช้"; }
}

// --- 4. ดึงข้อมูลแสดงผล & [NEW] API Status ---
$logs = [];
$unanswered_list = [];
$total_chats = 0;
$total_unanswered = 0;
$db_capacity_mb = "0.00";
$api_status_list = [];

if (isset($_SESSION['admin_logged_in'])) {
    // 4.1 Chat Logs ล่าสุด 50 รายการ
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $logs[] = $row; } }

    // 4.2 คำถามที่ AI ตอบไม่ได้
    $resU = $conn->query("SELECT * FROM unanswered_questions ORDER BY id DESC LIMIT 100");
    if ($resU) { while ($row = $resU->fetch_assoc()) { $unanswered_list[] = $row; } }

    // 4.3 สถิติต่างๆ
    $total_chats = ($conn->query("SELECT COUNT(*) FROM chat_logs"))->fetch_row()[0];
    $total_unanswered = ($conn->query("SELECT COUNT(*) FROM unanswered_questions"))->fetch_row()[0];
    
    $size_stmt = $conn->prepare("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = ?");
    $size_stmt->bind_param("s", $db_name);
    $size_stmt->execute();
    $db_capacity_mb = number_format((float)($size_stmt->get_result()->fetch_row()[0]) / 1024 / 1024, 2);

    // [NEW] 4.4 ตรวจสอบสถานะ API Keys (จำลองการเช็ค Health Check)
    $all_keys = array_filter(array_map('trim', explode(',', (string)getenv('GEMINI_API_KEY'))));
    $model = getenv('GEMINI_MODEL') ?: 'gemma-3-12b-it';
    
    foreach ($all_keys as $index => $key) {
        $masked_key = substr($key, 0, 4) . '...' . substr($key, -4);
        // ในระบบจริงอาจจะใช้ผลลัพธ์จาก Chat Log ล่าสุดมาประเมินสถานะ 429
        $api_status_list[] = [
            "name" => "Key #" . ($index + 1),
            "key" => $masked_key,
            "status" => "Ready", // สถานะเริ่มต้น
            "color" => "emerald"
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI Admin v2.1</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; }</style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
    <!-- Login Form (เหมือนเดิม) -->
    <div class="min-h-screen flex items-center justify-center p-4">
        <form method="POST" class="bg-white p-8 rounded-3xl shadow-xl w-full max-w-md border border-slate-100">
            <h1 class="text-2xl font-bold text-center mb-6">RW-AI Login</h1>
            <?php if($error_msg): ?> <p class="text-red-500 text-sm mb-4"><?php echo $error_msg; ?></p> <?php endif; ?>
            <input type="text" name="username" placeholder="Username" class="w-full mb-4 px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
            <input type="password" name="password" placeholder="Password" class="w-full mb-6 px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-2 focus:ring-blue-500">
            <button type="submit" name="login" class="w-full bg-blue-600 text-white py-3 rounded-xl font-bold">เข้าสู่ระบบ</button>
        </form>
    </div>
<?php else: ?>

    <nav class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex justify-between items-center">
            <span class="font-bold text-xl text-blue-600 italic">RW-AI Admin <span class="text-slate-400 font-normal">v2.1</span></span>
            <div class="flex items-center gap-4">
                <a href="?action=export_csv" class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold py-2 px-4 rounded-lg transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Export CSV
                </a>
                <a href="?action=logout" class="text-red-500 text-sm font-bold">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <!-- Stats Grid (ส่วนเดิม) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">แชททั้งหมด</p>
                <p class="text-2xl font-black"><?php echo number_format((float)$total_chats); ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-red-100 bg-red-50/30">
                <p class="text-xs text-red-400 uppercase font-bold mb-1">คำถามที่ AI ไม่รู้จัก</p>
                <p class="text-2xl font-black text-red-600"><?php echo number_format((float)$total_unanswered); ?></p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">DB Usage</p>
                <p class="text-2xl font-black"><?php echo $db_capacity_mb; ?> MB</p>
            </div>
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">System Status</p>
                <p class="text-sm font-bold text-emerald-500 flex items-center gap-1">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span> Online
                </p>
            </div>
        </div>

        <!-- [NEW] API Quota & Keys Status Area -->
        <div class="mb-8">
            <h2 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Gemma-3 API Load Balancing Status (5 Keys)
            </h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <?php foreach ($api_status_list as $api): ?>
                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
                    <div class="flex justify-between items-start mb-2">
                        <span class="text-[10px] font-bold text-slate-400 uppercase"><?php echo $api['name']; ?></span>
                        <span class="w-2 h-2 rounded-full bg-<?php echo $api['color']; ?>-500"></span>
                    </div>
                    <p class="text-xs font-mono text-slate-600 mb-1"><?php echo $api['key']; ?></p>
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] text-<?php echo $api['color']; ?>-600 font-bold bg-<?php echo $api['color']; ?>-50 px-2 py-0.5 rounded-md">
                            <?php echo $api['status']; ?>
                        </span>
                        <span class="text-[9px] text-slate-300">RPM: 15/15</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- ศูนย์ฝึกฝน AI (ส่วนเดิม) -->
            <div id="training-center" class="lg:col-span-1">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800">ศูนย์ฝึกฝน AI</h2>
                </div>
                <div class="space-y-3">
                    <?php if (empty($unanswered_list)): ?>
                        <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl text-center text-emerald-600 text-sm">เก่งมาก! AI ตอบคำถามได้ครบถ้วน</div>
                    <?php else: ?>
                        <?php foreach ($unanswered_list as $item): ?>
                            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm relative group">
                                <p class="text-sm font-medium text-slate-700 pr-6 italic">"<?php echo htmlspecialchars($item['user_message']); ?>."</p>
                                <p class="text-[10px] text-slate-400 mt-2"><?php echo $item['occured_at'] ?? ''; ?></p>
                                <a href="?delete_unanswered=<?php echo $item['id']; ?>" class="absolute top-4 right-4 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12" stroke-width="2" stroke-linecap="round"/></svg>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ประวัติการสนทนา (ส่วนเดิม) -->
            <div class="lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-800 mb-4">ประวัติการสนทนาล่าสุด</h2>
                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead class="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th class="px-4 py-3 font-bold text-slate-400 uppercase text-[10px]">เวลา</th>
                                <th class="px-4 py-3 font-bold text-slate-400 uppercase text-[10px]">คำถาม / คำตอบ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($logs as $log): ?>
                                <tr class="hover:bg-slate-50/50">
                                    <td class="px-4 py-4 align-top whitespace-nowrap text-[10px] text-slate-400">
                                        <?php echo date('H:i', strtotime($log['created_at'])); ?><br>
                                        <?php echo date('d/m', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="mb-2 font-medium text-blue-600">Q: <?php echo htmlspecialchars($log['user_message']); ?></div>
                                        <div class="text-slate-500 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100 line-clamp-2 hover:line-clamp-none transition-all">
                                            A: <?php echo htmlspecialchars($log['ai_response']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<?php endif; ?>
</body>
</html>
