<?php
set_time_limit(300); // เพิ่มเวลาให้ Script ทำงานได้นานขึ้นเป็น 5 นาที
/** * ระบบจดหมายเหตุโรงเรียน v1.3 - Aiven + Cloudinary AI + Skip Duplicates
 * เพิ่มเติม: ป้องกันการอัพโหลดรูปซ้ำในอัลบั้มเดียวกัน
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

    // ตัวแปรนับสถานะ
    $count_success = 0;
    $count_skip = 0;

    foreach ($_FILES['images']['name'] as $key => $name) {
        if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            // ดึงชื่อไฟล์เดิมมาคลีนตัวอักษรแปลกๆ ออก (เพื่อใช้เช็คซ้ำ)
            $safe_orig_name = preg_replace('/[^a-zA-Z0-9_-]/', '', pathinfo($name, PATHINFO_FILENAME));

            if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
                
                // รูปแบบชื่อไฟล์: ชื่ออัลบั้ม_ชื่อไฟล์เดิม.jpg (เช่น sport_day_IMG1234.jpg)
                $new_filename = $album_name . "_" . $safe_orig_name . "." . $ext;
                $target_path = $upload_dir . $new_filename;

                // --- 5. CHECK DUPLICATE (เช็คภาพซ้ำ) ---
                $is_duplicate = false;
                
                // เช็คว่ามีไฟล์นี้ในโฟลเดอร์แล้วหรือยัง
                if (file_exists($target_path)) {
                    $is_duplicate = true;
                } else {
                    // เช็คในฐานข้อมูลเผื่อกรณีไฟล์หายแต่ข้อมูลยังอยู่
                    $stmt_check = $conn->prepare("SELECT id FROM gallery_images WHERE album_name = ? AND file_name = ?");
                    $stmt_check->bind_param("ss", $album_name, $new_filename);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) {
                        $is_duplicate = true;
                    }
                    $stmt_check->close();
                }

                // ถ้าซ้ำ ให้บวกเลขไฟล์ข้ามและข้ามลูปนี้ไปเลย
                if ($is_duplicate) {
                    $count_skip++;
                    continue; 
                }

                // --- 6. AI AUTO BLUR LOGIC ---
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

                // --- 7. INSERT TO DB ---
                $stmt = $conn->prepare("INSERT INTO gallery_images (album_name, file_name, blur_status) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $album_name, $new_filename, $blur_status);
                $stmt->execute();
                
                $count_success++;
            }
        }
    }
    
    // แจ้งเตือนสถานะแบบแยกไฟล์สำเร็จและไฟล์ซ้ำ
    $message = "ดำเนินการเสร็จสิ้น! อัพโหลดใหม่ $count_success ไฟล์ ";
    if ($count_skip > 0) {
        $message .= "<br><span style='color: #800000;'>(ข้ามภาพที่เคยอัพโหลดแล้ว $count_skip ไฟล์)</span>";
    }
    
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



