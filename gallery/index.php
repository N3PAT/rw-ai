<?php
session_start(); // เริ่มระบบ Session สำหรับ Login
set_time_limit(300);
/** * ระบบจดหมายเหตุโรงเรียน v1.4 - Aiven + Cloudinary AI + Skip Duplicates + Auth
 * เพิ่มเติม: ระบบ Login เชื่อมต่อตาราง system_admins
 */

// --- 0. LOAD .ENV FILE ---
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

// --- 1. DATABASE CONFIG ---
$host    = getenv('DB_HOST');
$user    = getenv('DB_USER');   
$pass    = getenv('DB_PASS');   
$dbname  = getenv('DB_NAME');   
$port    = (int)(getenv('DB_PORT') ?: 14495);
$ca_cert = getenv('DB_SSL_CA') ?: 'ca.pem';

// --- 2. CLOUDINARY CONFIG ---
$cloudinary_url = getenv('CLOUDINARY_URL'); 

// --- 3. DATABASE CONNECTION (ย้ายขึ้นมาเชื่อมต่อก่อนเพื่อใช้ทำ Login) ---
$conn = mysqli_init();
$ca_path = __DIR__ . '/' . $ca_cert;

if (file_exists($ca_path)) {
    mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
}

$is_connected = @mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

if (!$is_connected) { 
    die("⚠️ DB Error: " . mysqli_connect_error()); 
}

// --- 4. LOGOUT LOGIC ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- 5. LOGIN LOGIC ---
$login_error = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $client_ip = $_SERVER['REMOTE_ADDR'];

    $stmt = $conn->prepare("SELECT password_hash, allowed_ip FROM system_admins WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($hash, $allowed_ip);
        $stmt->fetch();

        // ตรวจสอบรหัสผ่าน (รองรับ Hash แบบ BCRYPT ที่ให้มา)
        if (password_verify($password, $hash)) {
            // ตรวจสอบ IP (ถ้าไม่ใช่ 0.0.0.0 ให้เช็คว่าตรงกับ IP เครื่องที่เข้าไหม)
            if ($allowed_ip !== '0.0.0.0' && $allowed_ip !== $client_ip) {
                $login_error = "Access Denied: IP address ไม่ได้รับอนุญาต";
            } else {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
        } else {
            $login_error = "รหัสผ่านไม่ถูกต้อง";
        }
    } else {
        $login_error = "ไม่พบผู้ใช้งานในระบบ";
    }
    $stmt->close();
}

// --- 6. UPLOAD & PROCESS LOGIC (ทำงานเมื่อ Login แล้วเท่านั้น) ---
$message = "";
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
        
        $album_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['album_name']);
        $blur_status = isset($_POST['blur_faces']) ? 1 : 0;

        if (!is_dir('uploads')) {
            if (!@mkdir('uploads', 0755, true)) {
                die("⚠️ Fatal Error: ไม่สามารถสร้างโฟลเดอร์ uploads ได้");
            }
        }

        if (!is_writable('uploads')) {
            die("⚠️ Fatal Error: โฟลเดอร์ uploads ไม่ได้รับอนุญาตให้เขียนไฟล์");
        }

        $upload_dir = 'uploads/' . $album_name . '/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $count_success = 0;
        $count_skip = 0;

        foreach ($_FILES['images']['name'] as $key => $name) {
            if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $safe_orig_name = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($name, PATHINFO_FILENAME));

                if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    
                    $new_filename = $album_name . "_" . $safe_orig_name . "." . $ext;
                    $target_path = $upload_dir . $new_filename;

                    // CHECK DUPLICATE
                    $is_duplicate = false;
                    if (file_exists($target_path)) {
                        $is_duplicate = true;
                    } else {
                        $stmt_check = $conn->prepare("SELECT id FROM gallery_images WHERE album_name = ? AND file_name = ?");
                        $stmt_check->bind_param("ss", $album_name, $new_filename);
                        $stmt_check->execute();
                        $stmt_check->store_result();
                        if ($stmt_check->num_rows > 0) {
                            $is_duplicate = true;
                        }
                        $stmt_check->close();
                    }

                    if ($is_duplicate) {
                        $count_skip++;
                        continue; 
                    }

                    // AI AUTO BLUR LOGIC
                    $temp_path = $_FILES['images']['tmp_name'][$key];

                    if ($blur_status == 1 && !empty($cloudinary_url)) {
                        $url_parts = parse_url($cloudinary_url);
                        $api_key = $url_parts['user'];
                        $api_secret = $url_parts['pass'];
                        $cloud_name = $url_parts['host'];
                        $timestamp = time();

                        $params_to_sign = "timestamp=$timestamp&transformation=e_blur_faces:1000";
                        $signature = sha1($params_to_sign . $api_secret);

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/$cloud_name/image/upload");
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, [
                            'file' => new CURLFile($temp_path),
                            'transformation' => 'e_blur_faces:1000',
                            'api_key' => $api_key,
                            'timestamp' => $timestamp,
                            'signature' => $signature
                        ]);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        
                        $result = json_decode(curl_exec($ch), true);
                        curl_close($ch);

                        if (isset($result['secure_url'])) {
                            $img_data = @file_get_contents($result['secure_url']);
                            if ($img_data) {
                                file_put_contents($target_path, $img_data);
                            } else {
                                move_uploaded_file($temp_path, $target_path);
                            }
                        } else {
                            move_uploaded_file($temp_path, $target_path);
                        }
                    } else {
                        move_uploaded_file($temp_path, $target_path);
                    }

                    // INSERT TO DB
                    $stmt = $conn->prepare("INSERT INTO gallery_images (album_name, file_name, blur_status) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $album_name, $new_filename, $blur_status);
                    $stmt->execute();
                    
                    $count_success++;
                }
            }
        }
        
        $message = "ดำเนินการเสร็จสิ้น! อัพโหลดใหม่ $count_success ไฟล์ ";
        if ($count_skip > 0) {
            $message .= "<br><span style='color: #800000;'>(ข้ามภาพที่เคยอัพโหลดแล้ว $count_skip ไฟล์)</span>";
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>School Archive v1.4 - System</title>
    <style>
        :root { --bg: #c0c0c0; --blue: #000080; }
        body { background-color: #008080; font-family: 'Tahoma', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .window { background: var(--bg); border: 2px solid; border-color: #fff #808080 #808080 #fff; width: 90%; max-width: 450px; box-shadow: 2px 2px 0 #000; }
        .title-bar { background: linear-gradient(90deg, var(--blue), #1084d0); color: #fff; padding: 3px 6px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .title-bar a { color: #fff; text-decoration: none; font-size: 12px; background: #c0c0c0; color: black; padding: 1px 5px; border: 1px solid; border-color: #fff #808080 #808080 #fff; cursor: pointer; }
        .title-bar a:active { border-color: #808080 #fff #fff #808080; }
        .content { padding: 20px; }
        fieldset { border: 2px groove #fff; padding: 15px; margin-bottom: 15px; }
        input[type="text"], input[type="password"], input[type="file"] { width: 100%; box-sizing: border-box; background: #fff; border: 2px solid; border-color: #808080 #fff #fff #808080; padding: 5px; margin-top: 5px; }
        .blur-box { background: #ffffcc; border: 1px dashed #000; padding: 10px; margin-top: 10px; display: flex; align-items: center; gap: 10px; }
        button { width: 100%; padding: 10px; background: var(--bg); border: 2px solid; border-color: #fff #808080 #808080 #fff; font-weight: bold; cursor: pointer; }
        button:active { border-color: #808080 #fff #fff #808080; }
        .msg { font-weight: bold; text-align: center; margin-bottom: 10px; }
        .msg-success { color: #008000; }
        .msg-error { color: #ff0000; background: #ffe6e6; padding: 5px; border: 1px solid #ff0000; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true): ?>
    <div class="window">
        <div class="title-bar">
            <span>🔑 Security Logon</span>
            <span>X</span>
        </div>
        <div class="content">
            <?php if($login_error): ?> <div class="msg msg-error"><?php echo $login_error; ?></div> <?php endif; ?>
            <form action="" method="POST">
                <fieldset>
                    <legend>Admin Credentials</legend>
                    <label>Username:</label>
                    <input type="text" name="username" required>
                    <label style="margin-top:10px; display:block;">Password:</label>
                    <input type="password" name="password" required>
                </fieldset>
                <button type="submit" name="login_submit">LOGON</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="window">
        <div class="title-bar">
            <span>📷 Archive_Processor.exe (User: <?php echo $_SESSION['admin_username']; ?>)</span>
            <a href="?logout=true">Logout</a>
        </div>
        <div class="content">
            <?php if($message): ?> <div class="msg msg-success"><?php echo $message; ?></div> <?php endif; ?>
            <form action="" method="POST" enctype="multipart/form-data">
                <fieldset>
                    <legend>Gallery Info</legend>
                    <label>Album Name:</label>
                    <input type="text" name="album_name" required placeholder="เช่น sport_day_2007">
                    <label style="margin-top:10px; display:block;">Select Images/Folder:</label>
                    <input type="file" name="images[]" webkitdirectory directory multiple required>
                    <div class="blur-box">
                        <input type="checkbox" name="blur_faces" id="b" value="1">
                        <label for="b"><b>ใช้ AI ตรวจจับและเบลอหน้าบุคคล</b></label>
                    </div>
                </fieldset>
                <button type="submit">UPLOAD & AUTO-RENAME</button>
            </form>
        </div>
    </div>
<?php endif; ?>

</body>
</html>
