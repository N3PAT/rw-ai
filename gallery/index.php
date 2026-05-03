<?php
/** * ระบบจดหมายเหตุโรงเรียน v1.2 - Aiven + Cloudinary AI
 * แก้ไข: Permission Handling & Folder Auto-Correction
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

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
    
    // --- 3. DATABASE CONNECTION (MYSQLI WITH SSL) ---
    $conn = mysqli_init();
    $ca_path = __DIR__ . '/' . $ca_cert;

    if (file_exists($ca_path)) {
        mysqli_ssl_set($conn, NULL, NULL, $ca_path, NULL, NULL);
    }

    $is_connected = @mysqli_real_connect($conn, $host, $user, $pass, $dbname, $port, NULL, MYSQLI_CLIENT_SSL);

    if (!$is_connected) { 
        die("⚠️ DB Error: " . mysqli_connect_error()); 
    }

    // --- 4. FOLDER & PERMISSION MANAGEMENT ---
    $album_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['album_name']);
    $blur_status = isset($_POST['blur_faces']) ? 1 : 0;

    // ตรวจสอบโฟลเดอร์ uploads (หลัก)
    if (!is_dir('uploads')) {
        if (!@mkdir('uploads', 0755, true)) {
            die("⚠️ Fatal Error: ไม่สามารถสร้างโฟลเดอร์ uploads ได้ (Permission Denied) กรุณารัน sudo chmod 775 ใน Terminal");
        }
    }

    // ตรวจสอบความสามารถในการเขียนไฟล์
    if (!is_writable('uploads')) {
        die("⚠️ Fatal Error: โฟลเดอร์ uploads ไม่ได้รับอนุญาตให้เขียนไฟล์ (Permission Denied)");
    }

    $upload_dir = 'uploads/' . $album_name . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $count = 1;
    foreach ($_FILES['images']['name'] as $key => $name) {
        if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                
                $new_filename = sprintf("%03d", $count) . "_" . $album_name . "." . $ext;
                $temp_path = $_FILES['images']['tmp_name'][$key];
                $target_path = $upload_dir . $new_filename;

                // --- 5. AI AUTO BLUR LOGIC ---
                if ($blur_status == 1 && !empty($cloudinary_url)) {
                    $url_parts = parse_url($cloudinary_url);
                    $api_key = $url_parts['user'];
                    $api_secret = $url_parts['pass'];
                    $cloud_name = $url_parts['host'];
                    $timestamp = time();

                    // สร้าง Signature ที่ถูกต้อง
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
                        // ดึงไฟล์ที่เบลอแล้วจาก Cloudinary มาเซฟลงเครื่อง
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

                // --- 6. INSERT TO DB ---
                $stmt = $conn->prepare("INSERT INTO gallery_images (album_name, file_name, blur_status) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $album_name, $new_filename, $blur_status);
                $stmt->execute();
                
                $count++;
            }
        }
    }
    $message = "ดำเนินการเสร็จสิ้น! บันทึกเข้า Aiven DB แล้ว " . ($count-1) . " ไฟล์";
    $conn->close();
}
?>

                    

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>School Archive v1.1 - AI Powered</title>
    <style>
        :root { --bg: #c0c0c0; --blue: #000080; }
        body { background-color: #008080; font-family: 'Tahoma', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .window { background: var(--bg); border: 2px solid; border-color: #fff #808080 #808080 #fff; width: 90%; max-width: 450px; box-shadow: 2px 2px 0 #000; }
        .title-bar { background: linear-gradient(90deg, var(--blue), #1084d0); color: #fff; padding: 3px 6px; font-weight: bold; display: flex; justify-content: space-between; }
        .content { padding: 20px; }
        fieldset { border: 2px groove #fff; padding: 15px; margin-bottom: 15px; }
        input[type="text"], input[type="file"] { width: 100%; box-sizing: border-box; background: #fff; border: 2px solid; border-color: #808080 #fff #fff #808080; padding: 5px; margin-top: 5px; }
        .blur-box { background: #ffffcc; border: 1px dashed #000; padding: 10px; margin-top: 10px; display: flex; align-items: center; gap: 10px; }
        button { width: 100%; padding: 10px; background: var(--bg); border: 2px solid; border-color: #fff #808080 #808080 #fff; font-weight: bold; cursor: pointer; }
        button:active { border-color: #808080 #fff #fff #808080; }
        .msg { color: #008000; font-weight: bold; text-align: center; margin-bottom: 10px; }
    </style>
</head>
<body>
<div class="window">
    <div class="title-bar">
        <span>📷 Archive_Processor.exe</span>
        <span>X</span>
    </div>
    <div class="content">
        <?php if($message): ?> <div class="msg"><?php echo $message; ?></div> <?php endif; ?>
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
</body>
</html>



