<?php
/**
 * DATABASE CONNECTION (Render .env)
 * ดึงค่าจาก Environment Variables ที่ตั้งค่าไว้ใน Dashboard ของ Render
 */
$host = getenv('DB_HOST');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');
$dbname = getenv('DB_DATABASE');
$port = getenv('DB_PORT') ?: '3306'; // ค่าเริ่มต้น 3306

// ประมวลผลเมื่อมีการส่งฟอร์ม (POST)
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['images'])) {
    
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        die("<script>alert('เชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error . "');</script>");
    }

    $album_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['album_name']);
    $blur_status = isset($_POST['blur_faces']) ? 1 : 0;
    
    // สร้างโฟลเดอร์เก็บไฟล์ (ใน Render หากใช้ Disk ต้อง Map Path ให้ถูก)
    $upload_dir = 'uploads/' . $album_name . '/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $count = 1;
    $success_count = 0;

    foreach ($_FILES['images']['name'] as $key => $name) {
        if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($ext, $allowed)) {
                // เปลี่ยนชื่อไฟล์เป็น 001_albumname.jpg
                $new_filename = sprintf("%03d", $count) . "_" . $album_name . "." . $ext;
                $target_file = $upload_dir . $new_filename;

                if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $target_file)) {
                    // บันทึกลงตารางรูปภาพ
                    $stmt = $conn->prepare("INSERT INTO gallery_images (album_name, file_name, blur_status) VALUES (?, ?, ?)");
                    $stmt->bind_param("ssi", $album_name, $new_filename, $blur_status);
                    $stmt->execute();
                    
                    $count++;
                    $success_count++;
                }
            }
        }
    }
    $message = "สำเร็จ! อัปโหลดและเปลี่ยนชื่อไฟล์แล้ว $success_count รูป ในอัลบั้ม $album_name";
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Archive v1.0 (2001)</title>
    <style>
        /* Y2K Windows Retro Style */
        :root {
            --bg-color: #c0c0c0;
            --win-blue: #000080;
            --win-gray: #808080;
            --win-white: #ffffff;
        }

        body {
            background-color: #008080; /* สีเขียว Teal ยุค Win95/98 */
            font-family: 'Tahoma', sans-serif;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 10px;
        }

        .window {
            background: var(--bg-color);
            border-top: 2px solid var(--win-white);
            border-left: 2px solid var(--win-white);
            border-right: 2px solid var(--win-gray);
            border-bottom: 2px solid var(--win-gray);
            width: 100%;
            max-width: 450px;
            box-shadow: 2px 2px 0px #000;
        }

        .title-bar {
            background: linear-gradient(90deg, var(--win-blue), #1084d0);
            color: white;
            padding: 3px 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            font-size: 14px;
            letter-spacing: 1px;
        }

        .title-bar-controls button {
            width: 16px;
            height: 14px;
            font-size: 9px;
            background: var(--bg-color);
            border: 1px solid #000;
            padding: 0;
            margin-left: 2px;
            cursor: pointer;
        }

        .content {
            padding: 20px;
        }

        fieldset {
            border: 2px groove var(--win-white);
            padding: 15px;
            margin-bottom: 20px;
        }

        legend {
            font-size: 13px;
            color: #000;
            padding: 0 5px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-size: 13px;
            margin-bottom: 5px;
        }

        input[type="text"], input[type="file"] {
            width: 100%;
            box-sizing: border-box;
            background: #fff;
            border-top: 2px solid var(--win-gray);
            border-left: 2px solid var(--win-gray);
            border-right: 2px solid var(--win-white);
            border-bottom: 2px solid var(--win-white);
            padding: 5px;
            font-family: 'Tahoma';
        }

        .blur-box {
            background: #d4d4d4;
            border: 1px dashed #444;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        button.btn-submit {
            background: var(--bg-color);
            border-top: 2px solid var(--win-white);
            border-left: 2px solid var(--win-white);
            border-right: 2px solid var(--win-gray);
            border-bottom: 2px solid var(--win-gray);
            padding: 8px 25px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-family: 'Tahoma';
        }

        button.btn-submit:active {
            border-top: 2px solid var(--win-gray);
            border-left: 2px solid var(--win-gray);
            border-right: 2px solid var(--win-white);
            border-bottom: 2px solid var(--win-white);
        }

        .alert {
            background: #ffffcc;
            border: 1px solid #808080;
            padding: 10px;
            font-size: 12px;
            margin-bottom: 15px;
            color: #c00;
        }

        /* Mobile Adjustments */
        @media (max-width: 480px) {
            .window { border: none; }
            body { background: var(--bg-color); padding: 0; align-items: flex-start; }
        }
    </style>
</head>
<body>

<div class="window">
    <div class="title-bar">
        <span>📁 Gallery_Uploader.exe</span>
        <div class="title-bar-controls">
            <button>_</button>
            <button>□</button>
            <button style="background: #c0c0c0;">X</button>
        </div>
    </div>
    
    <div class="content">
        <?php if($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data">
            <fieldset>
                <legend>Upload Settings</legend>
                
                <div class="input-group">
                    <label>Album Name (ชื่ออัลบั้ม):</label>
                    <input type="text" name="album_name" required placeholder="example_2007">
                </div>

                <div class="input-group">
                    <label>Select Folder (เลือกทั้งโฟลเดอร์):</label>
                    <input type="file" name="images[]" webkitdirectory directory multiple required>
                </div>

                <div class="blur-box">
                    <input type="checkbox" name="blur_faces" id="blur" value="1">
                    <label for="blur" style="margin:0; font-weight:bold; color:#000080;">
                        Auto-Blur Faces in Folder
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="btn-submit">UPLOAD & RENAME</button>
        </form>

        <p style="font-size: 10px; color: #444; text-align: center; margin-top: 15px;">
            System: Windows PHP-Webkit Engine v1.0.2<br>
            © 2001-2026 School Memory Archive
        </p>
    </div>
</div>

</body>
</html>
