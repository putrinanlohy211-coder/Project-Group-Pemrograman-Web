<?php

session_start();
require_once 'config/db.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anggota Kelompok - Sistem Kantin</title>
    <link rel="stylesheet" href="css/style.css">

    <link rel="shortcut icon" href="ico/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="ico/favicon-16x16.png">
    <link rel="manifest" href="ico/site.webmanifest">
    
    <script src="js/main.js" defer></script>
    <style>
        .member-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .member-card {
            text-align: center;
            padding: 24px 16px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .member-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        .member-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            margin: 0 auto 14px auto;
            color: #fff;
        }
        .member-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-heading);
            margin-bottom: 4px;
        }
        .member-nim {
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

<header>
    <div class="container navbar">
        <a href="index.php" class="logo"><span>🍽️ Kantin Kita</span></a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Katalog Menu</a></li>
                <li><a href="anggota_kelompok.php" class="active">Anggota Kelompok</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'pelanggan'): ?>
                        <li><a href="keranjang.php">Keranjang</a></li>
                        <li><a href="dashboard_user.php">Pesanan Saya</a></li>
                    <?php elseif ($_SESSION['role'] === 'penjual'): ?>
                        <li><a href="dashboard_penjual.php">Dashboard Kantin</a></li>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="dashboard_admin.php">Dashboard Admin</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; margin-left: 10px;">Keluar</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline" style="padding: 6px 14px;">Masuk</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="max-width: 700px; margin: 60px auto;">

    <div style="text-align: center; margin-bottom: 40px;">
        <h1 style="font-size: 28px; font-weight: 700; color: var(--text-heading);">Anggota Kelompok</h1>
        <p style="color: #64748b; font-size: 15px;">Kelas Pemrograman Web C</p>
    </div>

    <div class="member-grid">
        <div class="member-card">
            <div class="member-avatar" style="background: #10b981; overflow: hidden; padding: 0;">
                <img src="images/putri.jpeg" alt="Mathilda Asthawa" 
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                    onerror="this.style.display='none'; this.parentElement.innerHTML='MA';"></div>
            <p class="member-name">Mathilda <br>A. P. N. Asthawa</p>
            <p class="member-nim">240211060093</p>
        </div>
        <div class="member-card">
            <div class="member-avatar" style="background: #3b82f6; overflow: hidden; padding: 0;">
                <img src="images/aika.jpeg" alt="Gaizka Rira"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                    onerror="this.style.display='none'; this.parentElement.innerHTML='GR';"></div>
            <p class="member-name">Gaizka <br>Alexandria Rira</p>
            <p class="member-nim">240211060013</p>
        </div>
        <div class="member-card">
            <div class="member-avatar" style="background: #f59e0b; overflow: hidden; padding: 0;">
                <img src="images/stacy.jpeg" alt="Stacy Rombepajung"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                    onerror="this.style.display='none'; this.parentElement.innerHTML='SR';"></div>
            <p class="member-name">Stacy V. T. Rombepajung</p>
            <p class="member-nim">240211060016</p>
        </div>
        <div class="member-card">
            <div class="member-avatar" style="background: #f43f5e; overflow: hidden; padding: 0;">
                <img src="images/bila.jpeg" alt="Nofia Komala Dewi"
                    style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;"
                    onerror="this.style.display='none'; this.parentElement.innerHTML='NK';"></div>
            <p class="member-name">Nofia <br>Komala Dewi</p>
            <p class="member-nim">240211060094</p>
        </div>
    </div>

    <div class="panel">
        <table style="width: 100%; font-size: 14px; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #64748b; width: 40%;">Program Studi</td>
                <td style="padding: 8px 0; font-weight: 600;">Teknik Informatika</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748b;">Fakultas</td>
                <td style="padding: 8px 0; font-weight: 600;">Teknik</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748b;">Universitas</td>
                <td style="padding: 8px 0; font-weight: 600;">Universitas Sam Ratulangi Manado</td>
            </tr>
        </table>
    </div>

</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> Kelompok UAS Pemrograman Web</p>
    </div>
</footer>

</body>
</html>