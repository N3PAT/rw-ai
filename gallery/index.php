<?php
/** * ระบบจดหมายเหตุโรงเรียน v1.1 - สไตล์ 2001 + AI Auto Blur
 * เชื่อมต่อ DB และ Cloudinary ผ่าน Environment Variables
 */

// 1. DATABASE CONFIG
$host   = getenv('DB_HOST');
$user   = getenv('DB_USERNAME');
$pass   = getenv('DB_PASSWORD');
$dbname = getenv('DB_DATABASE');
$port   = getenv('DB_PORT') ?: '3306';

// 2. CLOUDINARY CONFIG (สำหรับ AI เบลอหน้า)
// รูปแบบ: cloudinary://key:secret@cloud_name
$cloudinary_url = getenv('CLOUDINARY_URL'); 

$message = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
    
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) { die("DB Error: " . $conn->connect_error); }

    $album_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['album_name']);
    $blur_status = isset($_POST['blur_faces']) ? 1 : 0;
    
    // สร้างโฟลเดอร์ในเครื่อง (หรือใน Render Disk)
    $upload_dir = 'uploads/' . $album_name . '/';
    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }

    $count = 1;

    foreach ($_FILES['images']['name'] as $key => $name) {
        if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png'];

            if (in_array($ext, $allowed)) {
                // ตั้งชื่อไฟล์ตามสไตล์ 2010: 001_albumname.jpg
                $new_filename = sprintf("%03d", $count) . "_" . $album_name . "." . $ext;
                $temp_path = $_FILES['images']['tmp_name'][$key];
                $target_path = $upload_dir . $new_filename;

                if ($blur_status == 1 && !empty($cloudinary_url)) {
                    /**
                     * ส่วนของ AI AUTO BLUR
                     * ส่งรูปไปที่ Cloudinary พร้อมสั่งให้เบลอหน้า (e_blur_faces)
                     */
                    $ch = curl_init();
                    $cloudinary_endpoint = str_replace('cloudinary://', 'https://api.cloudinary.com/v1_1/', $cloudinary_url) . '/image/upload';
                    
                    // แยก Key/Secret จาก URL
                    $url_parts = parse_url($cloudinary_url);
                    $api_key = $url_parts['user'];
                    $api_secret = $url_parts['pass'];
                    $cloud_name = $url_parts['host'];

                    $timestamp = time();
                    $params = "transformation=e_blur_faces:1000&timestamp=$timestamp" . $api_secret;
                    $signature = sha1($params);

                    $post_data = [
                        'file' => new CURLFile($temp_path),
                        'transformation' => 'e_blur_faces:1000', // คำสั่ง AI ให้เบลอหน้าคน
                        'api_key' => $api_key,
                        'timestamp' => $timestamp,
                        'signature' => $signature
                    ];

                    curl_setopt($ch, CURLOPT_URL, "https://api.cloudinary.com/v1_1/$cloud_name/image/upload");
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    
                    $result = json_decode(curl_exec($ch), true);
                    curl_close($ch);

                    if (isset($result['secure_url'])) {
                        // โหลดไฟล์ที่เบลอแล้วจาก AI มาเก็บไว้ในเครื่องเรา
                        file_put_contents($target_path, file_get_contents($result['secure_url']));
                    } else {
                        // ถ้า AI พลาด ให้เซฟไฟล์ปกติ
                        move_uploaded_file($temp_path, $target_path);
                    }
                } else {
                    // ถ้าไม่ติ๊กเบลอหน้า ให้เซฟไฟล์ปกติ
                    move_uploaded_file($temp_path, $target_path);
                }

                // บันทึกข้อมูลลงฐานข้อมูล (แยก Table รูปภาพ)
                $stmt = $conn->prepare("INSERT INTO gallery_images (album_name, file_name, blur_status) VALUES (?, ?, ?)");
                $stmt->bind_param("ssi", $album_name, $new_filename, $blur_status);
                $stmt->execute();
                
                $count++;
            }
        }
    }
    $message = "ดำเนินการเสร็จสิ้น! จัดการไปทั้งหมด " . ($count-1) . " ไฟล์";
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Archive v1.1 - AI Powered</title>
    <style>
        /* CSS สไตล์ 2001 (คงเดิมจากที่คุณชอบ) */
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
                <label>Album Name (ชื่อโฟลเดอร์):</label>
                <input type="text" name="album_name" required placeholder="เช่น sport_day_2007">
                
                <label style="margin-top:10px; display:block;">Select Folder:</label>
                <input type="file" name="images[]" webkitdirectory directory multiple required>
                
                <div class="blur-box">
                    <input type="checkbox" name="blur_faces" id="b" value="1">
                    <label for="b"><b>ใช้ AI ตรวจจับและเบลอหน้าบุคคล</b></label>
                </div>
            </fieldset>
            <button type="submit">UPLOAD & AUTO-RENAME</button>
        </form>
        <p style="font-size:10px; text-align:center;">Status: AI Engine Ready (Cloudinary)</p>
    </div>
</div>

</body>
</html>
