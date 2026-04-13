<?php
declare(strict_types=1);
session_start();

/**
 * RW-AI Admin Dashboard (V2.0 - With Analytics & Training Center)
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

// --- 2. จัดการ Action ต่างๆ ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: admin.php");
    exit;
}

// ฟังก์ชันลบคำถามที่ตอบไม่ได้ (เมื่อแอดมินอ่านหรือจัดการแล้ว)
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
    // หมายเหตุ: อย่าลืมสร้างตาราง system_admins และใส่ user ไว้ด้วยนะครับ
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

// --- 4. ดึงข้อมูลแสดงผล ---
$logs = [];
$unanswered_list = [];
$total_chats = 0;
$total_unanswered = 0;
$db_capacity_mb = "0.00";

if (isset($_SESSION['admin_logged_in'])) {
    // 4.1 Chat Logs ล่าสุด 50 รายการ
    $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 50");
    if ($res) { while ($row = $res->fetch_assoc()) { $logs[] = $row; } }

    // 4.2 คำถามที่ AI ตอบไม่ได้ (จากที่ทำไว้เมื่อกี้)
    $resU = $conn->query("SELECT * FROM unanswered_questions ORDER BY id DESC LIMIT 100");
    if ($resU) { while ($row = $resU->fetch_assoc()) { $unanswered_list[] = $row; } }

    // 4.3 สถิติต่างๆ
    $total_chats = ($conn->query("SELECT COUNT(*) FROM chat_logs"))->fetch_row()[0];
    $total_unanswered = ($conn->query("SELECT COUNT(*) FROM unanswered_questions"))->fetch_row()[0];
    
    // DB Size
    $size_stmt = $conn->prepare("SELECT SUM(data_length + index_length) FROM information_schema.TABLES WHERE table_schema = ?");
    $size_stmt->bind_param("s", $db_name);
    $size_stmt->execute();
    $db_capacity_mb = number_format((float)($size_stmt->get_result()->fetch_row()[0]) / 1024 / 1024, 2);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI Admin v2.0</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; }</style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged_in'])): ?>
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
            <span class="font-bold text-xl text-blue-600 italic">RW-AI Admin <span class="text-slate-400 font-normal">v2.0</span></span>
            <a href="?action=logout" class="text-red-500 text-sm font-bold">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
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
                <p class="text-xs text-slate-400 uppercase font-bold mb-1">Status</p>
                <p class="text-sm font-bold text-emerald-500 flex items-center gap-1">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-ping"></span> Online
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div id="training-center" class="lg:col-span-1">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-bold text-slate-800">🎯 ศูนย์ฝึกฝน AI</h2>
                    <span class="px-2 py-1 bg-red-100 text-red-600 text-[10px] font-bold rounded-lg uppercase">ต้องการข้อมูลเพิ่ม</span>
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

            <div class="lg:col-span-2">
                <h2 class="text-lg font-bold text-slate-800 mb-4">💬 ประวัติการสนทนาล่าสุด</h2>
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
