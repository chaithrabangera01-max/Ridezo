<?php
require '../app/config.php';
checkUserRole('Admin');

$file = isset($_GET['file']) ? $_GET['file'] : '';
$title = isset($_GET['title']) ? htmlspecialchars($_GET['title']) : 'View Document';

if (empty($file) || !file_exists('../app/' . $file)) {
    die("Document not found.");
}

$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$is_pdf = ($ext === 'pdf');

$file_url = '../app/' . htmlspecialchars($file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> | Ridezo Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --accent: #22c55e;
            --bg: #020617;
            --surface: #0f172a;
            --surface-light: #1e293b;
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.08);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg); 
            color: var(--text); 
            margin: 0;
            padding: 40px;
            min-height: 100vh;
        }

        .page-header { display: flex; align-items: center; margin-bottom: 32px; gap: 16px; }
        .btn-back-page { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 14px; background: var(--surface-light); color: var(--text-muted); border: 1px solid var(--border); text-decoration: none; transition: var(--transition); flex-shrink: 0; }
        .btn-back-page:hover { background: var(--accent); color: var(--primary); border-color: var(--accent); }

        .viewer-container {
            background: var(--surface);
            border-radius: 24px;
            padding: 24px;
            border: 1px solid var(--border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 60vh;
        }

        .viewer-container img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 12px;
            object-fit: contain;
        }

        .viewer-container iframe {
            width: 100%;
            height: 70vh;
            border: none;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="page-header">
        <a href="javascript:history.back()" class="btn-back-page" title="Go Back"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 style="font-size: 1.8rem; font-weight: 800; letter-spacing: -1px; margin-bottom: 4px;"><?php echo $title; ?></h1>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Viewing document securely</p>
        </div>
        <a href="<?php echo $file_url; ?>" download style="margin-left: auto; background: var(--surface-light); color: var(--text); text-decoration: none; padding: 12px 24px; border-radius: 14px; font-weight: 700; border: 1px solid var(--border); display: flex; align-items: center; gap: 8px; transition: var(--transition);" onmouseover="this.style.background='var(--surface)'; this.style.borderColor='var(--text-muted)';" onmouseout="this.style.background='var(--surface-light)'; this.style.borderColor='var(--border)';">
            <i class="fas fa-download"></i> Download
        </a>
    </div>

    <div class="viewer-container">
        <?php if ($is_pdf): ?>
            <iframe src="<?php echo $file_url; ?>"></iframe>
        <?php else: ?>
            <img src="<?php echo $file_url; ?>" alt="<?php echo $title; ?>">
        <?php endif; ?>
    </div>
</body>
</html>
