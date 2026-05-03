<?php
declare(strict_types=1);

// เริ่ม Session สำหรับระบบ Rate Limit
session_start();

// --- 1. LOAD ENV ---
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

// --- 2. RATE LIMITING (ป้องกันการ Refresh รัวๆ จนเว็บล่ม) ---
$max_requests = 100; // จำนวนรีเควสสูงสุด
$window_seconds = 60; // ในระยะเวลา 60 วินาที
$currentTime = time();

if (!isset($_SESSION['rate_limit_time'])) {
    $_SESSION['rate_limit_time'] = $currentTime;
    $_SESSION['rate_limit_count'] = 1;
} else {
    $timePassed = $currentTime - $_SESSION['rate_limit_time'];
    if ($timePassed > $window_seconds) {
        $_SESSION['rate_limit_time'] = $currentTime;
        $_SESSION['rate_limit_count'] = 1;
    } else {
        $_SESSION['rate_limit_count']++;
        if ($_SESSION['rate_limit_count'] > $max_requests) {
            die("<!DOCTYPE html><html lang='th'><body style='background:#008080; color:white; font-family:Tahoma; text-align:center; padding:50px;'><h1>System Error: Too Many Requests</h1><p>คุณโหลดหน้าเว็บเร็วเกินไป กรุณารอสักครู่แล้วลองใหม่ครับ</p></body></html>");
        }
    }
}

// --- 3. DATABASE CONNECTION (PDO with SSL for Aiven) ---
// ดึงค่าจาก .env ให้ตรงตามที่ระบุมา
$host    = getenv('DB_HOST');
$user    = getenv('DB_USER');   // แก้จาก DB_USERNAME -> DB_USER
$pass    = getenv('DB_PASS');   // แก้จาก DB_PASSWORD -> DB_PASS
$dbname  = getenv('DB_NAME');   // แก้จาก DB_DATABASE -> DB_NAME
$port    = (int)(getenv('DB_PORT') ?: 14495);
$ca_cert = getenv('DB_SSL_CA') ?: 'ca.pem';

try {
    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    
    // ตั้งค่า Options สำหรับ PDO
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // ตรวจสอบและเปิดใช้งาน SSL ถ้ามีไฟล์ ca.pem
    $ca_path = __DIR__ . '/' . $ca_cert;
    if (file_exists($ca_path)) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca_path;
        // หากต้องการข้ามการ Verify Host ให้เปิดคอมเมนต์บรรทัดล่าง (กรณีรันบน Local บางตัว)
        // $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }

    $pdo = new PDO($dsn, $user, $pass, $options);

} catch (PDOException $e) {
    // แสดง Error แบบละเอียดเพื่อการตรวจสอบ (Debug)
    die("<!DOCTYPE html><html><body style='background:#008080; color:white; font-family:Tahoma; text-align:center; padding:50px;'>
        <h1>Fatal Error: Database Connection Failed</h1>
        <div style='background:#fff; color:#000; padding:15px; border:2px solid #808080; display:inline-block; text-align:left;'>
            <strong>Error Detail:</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>
        <p style='margin-top:20px;'>* ตรวจสอบว่าไฟล์ <b>{$ca_cert}</b> อยู่ในโฟลเดอร์เดียวกับไฟล์ PHP หรือไม่?</p>
    </body></html>");
}

// --- 4. ดึงข้อมูล (แก้ไข Query ให้รองรับ only_full_group_by) ---
try {
    $selected_album = isset($_GET['album']) ? (string)$_GET['album'] : null;

    // ใช้ MAX(created_at) เพื่อดึงวันที่ล่าสุดของรูปในอัลบั้มนั้นมาเรียงลำดับ
    $album_query = "SELECT album_name, COUNT(*) as total_images 
                    FROM gallery_images 
                    GROUP BY album_name 
                    ORDER BY MAX(created_at) DESC"; 
    
    $albums_stmt = $pdo->query($album_query);
    $albums = $albums_stmt->fetchAll();
    $total_albums = count($albums);
} catch (PDOException $e) {
    die("Query Error: " . $e->getMessage());
}


?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RW-AI OLD GALLERY</title>
    <style>
        :root {
            --bg-color: #c0c0c0;
            --win-blue: #000080;
            --win-gray: #808080;
            --win-white: #ffffff;
        }

        body {
            background-color: #008080; /* Classic Teal */
            font-family: 'Tahoma', sans-serif;
            margin: 0;
            padding: 10px;
        }

        /* Layout หลัก */
        .desktop {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .window {
            background: var(--bg-color);
            border-top: 2px solid var(--win-white);
            border-left: 2px solid var(--win-white);
            border-right: 2px solid var(--win-gray);
            border-bottom: 2px solid var(--win-gray);
            box-shadow: 2px 2px 0px #000;
        }

        .title-bar {
            background: linear-gradient(90deg, var(--win-blue), #1084d0);
            color: white;
            padding: 4px 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: bold;
            font-size: 14px;
        }

        .menu-bar {
            border-bottom: 1px solid var(--win-gray);
            padding: 2px 5px;
            font-size: 12px;
            display: flex;
            gap: 10px;
        }

        /* โซนแสดงอัลบั้ม (Folder View) */
        .folder-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 15px;
            padding: 20px;
            background: #fff;
            margin: 2px;
            min-height: 200px;
            border-top: 2px solid var(--win-gray);
            border-left: 2px solid var(--win-gray);
        }

        .folder-item {
            text-align: center;
            text-decoration: none;
            color: #000;
            font-size: 12px;
            cursor: pointer;
        }

        .folder-icon {
            width: 48px;
            height: 48px;
            background: #ffd700;
            margin: 0 auto 5px;
            position: relative;
            border: 1px solid #b8860b;
            box-shadow: 1px 1px 0 #000;
        }

        .folder-icon::before {
            content: "";
            position: absolute;
            top: -5px;
            left: -1px;
            width: 20px;
            height: 5px;
            background: #ffd700;
            border: 1px solid #b8860b;
            border-bottom: none;
        }

        /* โซนแสดงรูปภาพ (Thumbnail View) */
        .image-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            padding: 15px;
            background: #fff;
            margin: 2px;
        }

        .photo-card {
            border: 1px solid #ccc;
            padding: 5px;
            background: #f9f9f9;
        }

        .photo-card img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            display: block;
        }

        .photo-name {
            font-size: 10px;
            margin-top: 5px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .btn-back {
            display: inline-block;
            padding: 4px 10px;
            background: var(--bg-color);
            border-top: 1px solid #fff;
            border-left: 1px solid #fff;
            border-right: 1px solid #000;
            border-bottom: 1px solid #000;
            text-decoration: none;
            color: #000;
            font-size: 12px;
            margin: 10px;
        }

        /* Responsive */
        @media (max-width: 600px) {
            .image-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="desktop">
    
    <div class="window">
        <div class="title-bar">
            <span>C:\MyDocuments\RittiyaArchive\<?php echo $selected_album ? htmlspecialchars($selected_album) : "Albums"; ?></span>
            <div style="font-size:10px;">_ □ X</div>
        </div>
        <div class="menu-bar">
            <span><u>F</u>ile</span>
            <span><u>E</u>dit</span>
            <span><u>V</u>iew</span>
            <span><u>H</u>elp</span>
        </div>

        <?php if ($selected_album): ?>
            <a href="?" class="btn-back">⬅ Back to Albums</a>
            <div class="image-grid">
                <?php
                // ใช้ PDO เตรียม Statement ดึงรูปภาพ
                $stmt = $pdo->prepare("SELECT file_name FROM gallery_images WHERE album_name = ?");
                $stmt->execute([$selected_album]);
                $photos = $stmt->fetchAll();
                
                foreach ($photos as $photo): 
                    $safe_file_name = htmlspecialchars($photo['file_name']);
                    $img_path = "uploads/" . htmlspecialchars($selected_album) . "/" . $safe_file_name;
                ?>
                    <div class="photo-card">
                        <img src="<?php echo $img_path; ?>" alt="Photo">
                        <div class="photo-name" title="<?php echo $safe_file_name; ?>"><?php echo $safe_file_name; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="folder-grid">
                <?php foreach ($albums as $row): ?>
                    <a href="?album=<?php echo urlencode($row['album_name']); ?>" class="folder-item">
                        <div class="folder-icon"></div>
                        <div><?php echo htmlspecialchars($row['album_name']); ?></div>
                        <div style="font-size: 10px; color: #666;">(<?php echo $row['total_images']; ?> items)</div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<p style="text-align: center; font-size: 11px; color: white; margin-top: 20px;">
    Total Albums Found: <?php echo $total_albums; ?> | Powered by RW-AI
</p>

</body>
</html>
