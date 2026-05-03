<?php
// DATABASE CONNECTION (Render .env)
$host = getenv('DB_HOST');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');
$dbname = getenv('DB_DATABASE');
$port = getenv('DB_PORT') ?: '3306';

$conn = new mysqli($host, $user, $pass, $dbname, $port);
if ($conn->connect_error) { die("Connection failed"); }

// ดึงรายชื่ออัลบั้มทั้งหมด (Group By album_name)
$album_query = "SELECT album_name, COUNT(*) as total_images FROM gallery_images GROUP BY album_name ORDER BY created_at DESC";
$albums = $conn->query($album_query);

// ตรวจสอบว่ามีการเลือกอัลบั้มไหนอยู่หรือไม่
$selected_album = isset($_GET['album']) ? $_GET['album'] : null;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Memory Archive 2001</title>
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
            <a href="index.php" class="btn-back">⬅ Back to Albums</a>
            <div class="image-grid">
                <?php
                $stmt = $conn->prepare("SELECT file_name FROM gallery_images WHERE album_name = ?");
                $stmt->bind_param("s", $selected_album);
                $stmt->execute();
                $photos = $stmt->get_result();
                
                while($photo = $photos->fetch_assoc()): 
                    $img_path = "uploads/" . $selected_album . "/" . $photo['file_name'];
                ?>
                    <div class="photo-card">
                        <img src="<?php echo $img_path; ?>" alt="Photo">
                        <div class="photo-name"><?php echo $photo['file_name']; ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="folder-grid">
                <?php while($row = $albums->fetch_assoc()): ?>
                    <a href="?album=<?php echo urlencode($row['album_name']); ?>" class="folder-item">
                        <div class="folder-icon"></div>
                        <div><?php echo htmlspecialchars($row['album_name']); ?></div>
                        <div style="font-size: 10px; color: #666;">(<?php echo $row['total_images']; ?> items)</div>
                    </a>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<p style="text-align: center; font-size: 11px; color: white; margin-top: 20px;">
    Total Albums Found: <?php echo $albums->num_rows; ?> | Powered by RW-AI
</p>

</body>
</html>
