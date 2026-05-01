<?php
declare(strict_types=1);
session_start();

/**
 * RW-AI Admin Dashboard (V2.2 - With Dynamic Database Manager)
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

// --- 2. ACTIONS ---
if (isset($_GET['action']) && $_GET['action'] === 'export_csv' && isset($_SESSION['admin_logged_in'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=chat_history_'.date('Y-m-d').'.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'IP Address', 'User Message', 'AI Response', 'Created At']);
    $res = $conn->query("SELECT id, ip_address, user_message, ai_response, created_at FROM chat_logs ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) { fputcsv($output, $row); }
    fclose($output);
    exit;
}

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

// [NEW] ACTION: Dynamic Database Delete
if (isset($_GET['action']) && $_GET['action'] === 'db_delete' && isset($_SESSION['admin_logged_in'])) {
    $table = $_GET['table'] ?? '';
    $pk_col = $_GET['pk'] ?? '';
    $pk_val = $_GET['id'] ?? '';
    
    // ตรวจสอบความปลอดภัยเบื้องต้น ป้องกัน SQL Injection บนชื่อ Table
    $res = $conn->query("SHOW TABLES");
    $allowed_tables = [];
    while($row = $res->fetch_array()) { $allowed_tables[] = $row[0]; }
    
    if (in_array($table, $allowed_tables) && !empty($pk_col) && !empty($pk_val)) {
        $stmt = $conn->prepare("DELETE FROM `$table` WHERE `$pk_col` = ?");
        $stmt->bind_param("s", $pk_val);
        $stmt->execute();
    }
    header("Location: admin.php?page=db_manager&table=" . urlencode($table));
    exit;
}

// [NEW] ACTION: Dynamic Database Insert
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'dynamic_insert' && isset($_SESSION['admin_logged_in'])) {
    $table = $_POST['table'];
    $data = $_POST['data'] ?? [];
    
    $cols = []; $vals = []; $types = ""; $binds = [];
    foreach ($data as $col => $val) {
        if ($val !== '') { // ไม่เอาค่าว่าง
            $cols[] = "`$col`";
            $vals[] = "?";
            $types .= "s"; // มองเป็น string ไปก่อนเพื่อความปลอดภัย
            $binds[] = $val;
        }
    }
    
    if (!empty($cols)) {
        $sql = "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$binds);
        $stmt->execute();
    }
    header("Location: admin.php?page=db_manager&table=" . urlencode($table));
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
            header("Location: admin.php");
            exit;
        } else { $error_msg = "รหัสผ่านไม่ถูกต้อง"; }
    } else { $error_msg = "ไม่พบชื่อผู้ใช้"; }
}

// โหลดข้อมูล Dashboard
$current_page = $_GET['page'] ?? 'dashboard';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI Admin v2.2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            <div class="flex items-center gap-6">
                <span class="font-bold text-xl text-blue-600 italic">RW-AI <span class="text-slate-400 font-normal">v2.2</span></span>
                <div class="hidden md:flex gap-2">
                    <a href="?page=dashboard" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'dashboard' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50'; ?>">Dashboard</a>
                    <a href="?page=db_manager" class="px-4 py-2 rounded-lg text-sm font-medium <?php echo $current_page === 'db_manager' ? 'bg-blue-50 text-blue-600' : 'text-slate-500 hover:bg-slate-50'; ?>">Database Manager</a>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <a href="?action=logout" class="text-red-500 text-sm font-bold">ออกจากระบบ</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-6 py-8">
        
        <?php if ($current_page === 'dashboard'): ?>
            <?php
                // ดึงข้อมูลสถิติ
                $total_chats = ($conn->query("SELECT COUNT(*) FROM chat_logs"))->fetch_row()[0];
                $total_unanswered = ($conn->query("SELECT COUNT(*) FROM unanswered_questions"))->fetch_row()[0];
                $logs = []; $res = $conn->query("SELECT * FROM chat_logs ORDER BY id DESC LIMIT 50");
                if ($res) while ($row = $res->fetch_assoc()) $logs[] = $row;
            ?>
            <div class="flex justify-between items-end mb-6">
                <h2 class="text-2xl font-bold text-slate-800">System Overview</h2>
                <a href="?action=export_csv" class="bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-bold py-2 px-4 rounded-lg transition-colors">Export Chat CSV</a>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                    <p class="text-xs text-slate-400 uppercase font-bold mb-1">แชททั้งหมด</p>
                    <p class="text-2xl font-black"><?php echo number_format((float)$total_chats); ?></p>
                </div>
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-red-100 bg-red-50/30">
                    <p class="text-xs text-red-400 uppercase font-bold mb-1">คำถามที่ตอบไม่ได้</p>
                    <p class="text-2xl font-black text-red-600"><?php echo number_format((float)$total_unanswered); ?></p>
                </div>
            </div>

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
                                    <?php echo date('d/m H:i', strtotime($log['created_at'])); ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="mb-2 font-medium text-blue-600">Q: <?php echo htmlspecialchars($log['user_message']); ?></div>
                                    <div class="text-slate-500 text-xs bg-slate-50 p-3 rounded-lg border border-slate-100">
                                        A: <?php echo htmlspecialchars($log['ai_response']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($current_page === 'db_manager'): ?>
            <?php
                // ดึงรายชื่อ Table ทั้งหมด
                $tables = [];
                $res_tables = $conn->query("SHOW TABLES");
                while($row = $res_tables->fetch_array()) { $tables[] = $row[0]; }
                
                $selected_table = $_GET['table'] ?? ($tables[0] ?? '');
                $search_query = $_GET['search'] ?? '';
            ?>
            
            <div class="flex flex-col lg:flex-row gap-6">
                <div class="w-full lg:w-64 flex-shrink-0">
                    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
                        <h3 class="text-sm font-bold text-slate-400 uppercase mb-3 px-2">Tables</h3>
                        <div class="space-y-1">
                            <?php foreach($tables as $tbl): ?>
                                <a href="?page=db_manager&table=<?php echo urlencode($tbl); ?>" 
                                   class="block px-3 py-2 rounded-lg text-sm font-medium transition-colors <?php echo $tbl === $selected_table ? 'bg-blue-50 text-blue-600' : 'text-slate-600 hover:bg-slate-50'; ?>">
                                    <svg class="w-4 h-4 inline-block mr-1 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                                    <?php echo $tbl; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="flex-1 min-w-0">
                    <?php if ($selected_table): ?>
                        <?php
                            // ดึงโครงสร้าง (Columns)
                            $columns = [];
                            $primary_key = '';
                            $res_cols = $conn->query("SHOW COLUMNS FROM `$selected_table`");
                            while($col = $res_cols->fetch_assoc()) {
                                $columns[] = $col;
                                if ($col['Key'] === 'PRI') $primary_key = $col['Field'];
                            }

                            // สร้างเงื่อนไขการค้นหา
                            $where_clause = "";
                            if (!empty($search_query)) {
                                $search_conditions = [];
                                foreach ($columns as $col) {
                                    $search_conditions[] = "`{$col['Field']}` LIKE '%" . $conn->real_escape_string($search_query) . "%'";
                                }
                                $where_clause = " WHERE " . implode(" OR ", $search_conditions);
                            }

                            // ดึงข้อมูล (จำกัด 100 แถวเพื่อความรวดเร็ว)
                            $table_data = [];
                            $res_data = $conn->query("SELECT * FROM `$selected_table` $where_clause ORDER BY 1 DESC LIMIT 100");
                            if ($res_data) {
                                while($row = $res_data->fetch_assoc()) { $table_data[] = $row; }
                            }
                        ?>

                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4 gap-4">
                            <div>
                                <h2 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                                    <?php echo $selected_table; ?>
                                </h2>
                                <p class="text-sm text-slate-500">พบข้อมูล <?php echo count($table_data); ?> รายการ (แสดงสูงสุด 100 รายการ)</p>
                            </div>
                            
                            <form method="GET" class="flex gap-2 w-full md:w-auto">
                                <input type="hidden" name="page" value="db_manager">
                                <input type="hidden" name="table" value="<?php echo htmlspecialchars($selected_table); ?>">
                                <input type="text" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="ค้นหาข้อมูล..." class="px-4 py-2 border border-slate-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 w-full md:w-64">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-bold">ค้นหา</button>
                            </form>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6 mb-6">
                            <h3 class="text-sm font-bold text-slate-800 mb-4">เพิ่มข้อมูลใหม่</h3>
                            <form method="POST" action="?page=db_manager" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <input type="hidden" name="action" value="dynamic_insert">
                                <input type="hidden" name="table" value="<?php echo htmlspecialchars($selected_table); ?>">
                                <?php foreach ($columns as $col): ?>
                                    <?php if ($col['Extra'] !== 'auto_increment' && $col['Field'] !== 'created_at'): ?>
                                        <div>
                                            <label class="block text-xs font-bold text-slate-500 mb-1"><?php echo $col['Field']; ?> <span class="font-normal text-[10px]">(<?php echo $col['Type']; ?>)</span></label>
                                            <input type="text" name="data[<?php echo $col['Field']; ?>]" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm" placeholder="<?php echo $col['Null'] === 'YES' ? 'Optional' : 'Required'; ?>">
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <div class="md:col-span-2 flex justify-end mt-2">
                                    <button type="submit" class="bg-emerald-500 text-white px-6 py-2 rounded-lg text-sm font-bold hover:bg-emerald-600">บันทึกข้อมูล</button>
                                </div>
                            </form>
                        </div>

                        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-x-auto mb-8">
                            <table class="w-full text-left border-collapse text-sm whitespace-nowrap">
                                <thead class="bg-slate-50 border-b border-slate-100">
                                    <tr>
                                        <th class="px-4 py-3 font-bold text-slate-400 uppercase text-[10px]">Action</th>
                                        <?php foreach($columns as $col): ?>
                                            <th class="px-4 py-3 font-bold text-slate-600 uppercase text-[10px]" title="Type: <?php echo $col['Type']; ?>">
                                                <?php echo $col['Field']; ?>
                                                <?php if($col['Key'] === 'PRI') echo '<span class="text-amber-500 ml-1">🔑</span>'; ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <?php if (empty($table_data)): ?>
                                        <tr><td colspan="<?php echo count($columns) + 1; ?>" class="px-4 py-8 text-center text-slate-400">ไม่พบข้อมูล</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($table_data as $row): ?>
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-4 py-3">
                                                    <?php if ($primary_key): ?>
                                                        <a href="?action=db_delete&table=<?php echo urlencode($selected_table); ?>&pk=<?php echo urlencode($primary_key); ?>&id=<?php echo urlencode((string)$row[$primary_key]); ?>" 
                                                           class="text-red-500 hover:text-red-700 font-bold text-xs bg-red-50 px-2 py-1 rounded"
                                                           onclick="return confirm('ยืนยันการลบข้อมูลนี้?');">Delete</a>
                                                    <?php else: ?>
                                                        <span class="text-[10px] text-slate-300">No PK</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php foreach($columns as $col): ?>
                                                    <td class="px-4 py-3 text-slate-600 max-w-xs truncate" title="<?php echo htmlspecialchars((string)$row[$col['Field']]); ?>">
                                                        <?php echo htmlspecialchars((string)$row[$col['Field']]); ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
<?php endif; ?>
</body>
</html>
