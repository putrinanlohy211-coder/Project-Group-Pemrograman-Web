<?php

session_start();
require_once 'config/db.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';
$cat_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$warung_filter = isset($_GET['warung']) ? mysqli_real_escape_string($conn, trim($_GET['warung'])) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    if (isset($_SESSION['role']) && $_SESSION['role'] !== 'pelanggan') {
        echo "<script>alert('Akses Ditolak: Hanya akun pelanggan yang dapat memesan makanan!'); window.location.href='index.php';</script>";
        exit;
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }

    $menu_id = intval($_POST['menu_id']);
    $qty = isset($_POST['qty']) ? intval($_POST['qty']) : 1;

    $check_menu = mysqli_query($conn, "SELECT id, ketersediaan FROM menu WHERE id = $menu_id");
    if (mysqli_num_rows($check_menu) > 0) {
        $menu_item = mysqli_fetch_assoc($check_menu);
        if ($menu_item['ketersediaan'] == 0) {
            echo "<script>alert('Maaf, menu tersebut sedang habis!'); window.location.href='index.php';</script>";
            exit;
        }

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$menu_id])) {
            $_SESSION['cart'][$menu_id] += $qty;
        } else {
            $_SESSION['cart'][$menu_id] = $qty;
        }

        header("Location: index.php");
        exit;
    }
}

$categories_result = mysqli_query($conn, "SELECT * FROM kategori ORDER BY id ASC");
if (!$categories_result) {
    die("Database Connection Error: Gagal memuat tabel kategori.");
}

$warungs_result = mysqli_query($conn, "SELECT DISTINCT nama_warung FROM menu WHERE nama_warung IS NOT NULL AND nama_warung != '' ORDER BY nama_warung ASC");

$query = "SELECT m.*, k.nama_kategori FROM menu m LEFT JOIN kategori k ON m.kategori_id = k.id WHERE m.ketersediaan = 1";

if (!empty($search)) {
    $query .= " AND (m.nama_menu LIKE '%$search%' OR m.deskripsi LIKE '%$search%')";
}
if ($cat_id > 0) {
    $query .= " AND m.kategori_id = $cat_id";
}
if (!empty($warung_filter)) {
    $query .= " AND m.nama_warung = '$warung_filter'";
}

$query .= " ORDER BY m.id DESC";
$menu_result = mysqli_query($conn, $query);

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $menu_id => $qty) {
        $cart_count += $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesan Makanan Kantin - Sistem Online Kantin</title>
    <link rel="stylesheet" href="css/style.css">

    <link rel="shortcut icon" href="ico/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="ico/favicon-16x16.png">
    <link rel="manifest" href="ico/site.webmanifest">

    <script src="js/main.js" defer></script>
</head>
<body>

<header>
    <div class="container">
        <div class="navbar">
        <a href="index.php" class="logo">
            <span>🍽️ Kantin Kita</span>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php" class="active">Katalog Menu</a></li>
                <li><a href="anggota_kelompok.php">Anggota Kelompok</a></li>
                
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] === 'pelanggan'): ?>
                        <li><a href="keranjang.php">Keranjang <span class="badge"><?= $cart_count ?></span></a></li>
                        <li><a href="dashboard_user.php">Pesanan Saya</a></li>
                    <?php elseif ($_SESSION['role'] === 'penjual'): ?>
                        <li><a href="dashboard_penjual.php"><b>Dashboard Kantin</b></a></li>
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <li><a href="dashboard_admin.php"><b>Dashboard Admin</b></a></li>
                    <?php endif; ?>
                    
                    <li style="border-left: 1px solid var(--border); padding-left: 15px;">
                        <span style="font-size: 13px; color: var(--text-heading); font-weight: 500;">
                            Halo, <?= htmlspecialchars($_SESSION['username']) ?>!
                        </span>
                    </li>
                    <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; margin-left:10px;">Keluar</a></li>
                <?php else: ?>
                    <li><a href="login.php" class="btn btn-outline" style="padding: 6px 14px;">Masuk</a></li>
                    <li><a href="register.php" class="btn btn-primary" style="padding: 6px 14px; color: white;">Daftar</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</div>
</header>

<main class="container">
    <div class="hero">
        <h1>Sistem Pemesanan Makanan Kantin</h1>
        <p>Solusi praktis pesan makanan dan minuman tanpa perlu antre panjang. Pilih menu favorit Anda dan ambil saat hidangan sudah siap disajikan!</p>
    </div>

    <div class="panel" style="margin-bottom: 30px; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
        <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 10px; width: 100%; max-width: 680px; margin: 0;">
            <input type="text" name="search" class="form-control" placeholder="Cari Nasi Goreng, Es Teh, Batagor..." value="<?= htmlspecialchars($search) ?>" style="flex: 1; min-width: 180px;">
            <select name="warung" class="form-control" style="width: auto; min-width: 170px;" onchange="this.form.submit()">
                <option value="">Semua Warung Penjual</option>
                <?php 
                if ($warungs_result) {
                    mysqli_data_seek($warungs_result, 0);
                    while ($w = mysqli_fetch_assoc($warungs_result)) {
                        $sel = ($warung_filter === $w['nama_warung']) ? 'selected' : '';
                        echo '<option value="'.htmlspecialchars($w['nama_warung']).'" '.$sel.'>🏪 '.htmlspecialchars($w['nama_warung']).'</option>';
                    }
                }
                ?>
            </select>
            <?php if ($cat_id > 0): ?>
                <input type="hidden" name="category" value="<?= $cat_id ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">Cari</button>
            <?php if (!empty($search) || $cat_id > 0 || !empty($warung_filter)): ?>
                <a href="index.php" class="btn btn-outline">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 14px; font-weight: 500;">
            Menampilkan: <span style="color: var(--primary); font-weight: 600;"><?= mysqli_num_rows($menu_result) ?> Menu Hidangan</span>
        </div>
    </div>

    <div class="category-filter">
        <a href="index.php<?= !empty($search) ? '?search='.urlencode($search) : '' ?><?= !empty($warung_filter) ? '&warung='.urlencode($warung_filter) : '' ?>" class="filter-btn <?= ($cat_id == 0) ? 'active' : '' ?>">Semua Kategori</a>
        <?php while ($cat = mysqli_fetch_assoc($categories_result)): ?>
            <a href="index.php?category=<?= $cat['id'] ?><?= !empty($search) ? '&search='.urlencode($search) : '' ?><?= !empty($warung_filter) ? '&warung='.urlencode($warung_filter) : '' ?>" 
               class="filter-btn <?= ($cat_id == $cat['id']) ? 'active' : '' ?>">
                <?= htmlspecialchars($cat['nama_kategori']) ?>
            </a>
        <?php endwhile; ?>
    </div>

    <div class="menu-grid">
        <?php if (mysqli_num_rows($menu_result) > 0): ?>
            <?php while ($menu = mysqli_fetch_assoc($menu_result)): ?>
                <div class="menu-card">
                    <div class="menu-image">
                        <img src="<?= getMenuImage($menu['nama_menu'], $menu['gambar']) ?>" alt="<?= htmlspecialchars($menu['nama_menu']) ?>" referrerpolicy="no-referrer">
                        <span class="item-badge"><?= htmlspecialchars($menu['nama_kategori']) ?></span>
                    </div>
                    <div class="menu-body" style="padding: 16px;">
                        <?php if (!empty($menu['nama_warung'])): ?>
                            <span style="font-size: 11px; color: var(--primary); font-weight: 700; margin-bottom: 4px; display: block;">🏪 <?= htmlspecialchars($menu['nama_warung']) ?></span>
                        <?php endif; ?>
                        <h3 class="menu-title" style="font-size: 15px; font-weight: 700; color: var(--text-heading); margin-bottom: 6px;"><?= htmlspecialchars($menu['nama_menu']) ?></h3>
                        <p class="menu-desc" style="font-size: 12px; color: var(--text-main); margin-bottom: 12px; line-height: 1.4;"><?= htmlspecialchars($menu['deskripsi']) ?></p>
                        
                        <div class="menu-footer" style="padding-top: 10px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                            <span class="menu-price" style="font-size: 15px; font-weight: 800; color: var(--primary);">Rp <?= number_format($menu['harga'], 0, ',', '.') ?></span>
                            
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="menu_id" value="<?= $menu['id'] ?>">
                                <input type="hidden" name="action" value="add_to_cart">
                                <?php if (isset($_SESSION['role']) && $_SESSION['role'] !== 'pelanggan'): ?>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary" style="padding: 6px 12.5px; font-size: 11.5px; font-weight:600;">+ Pesan</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="panel" style="grid-column: 1 / -1; text-align: center; padding: 40px 20px;">
                <p style="font-size: 15px; color: var(--text-main); margin-bottom: 4px; font-weight: 600;">Katalog Hidangan Kosong</p>
                <p style="font-size: 12px; color: #94a3b8;">Cobalah mengganti filter pencarian atau bersihkan filter warung.</p>
                <a href="index.php" class="btn btn-outline" style="margin-top: 12px; font-size:11.5px;">Lihat Semua Menu</a>
            </div>
        <?php endif; ?>
    </div>
</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> Kelompok UAS Pemrograman Web - Sistem Pemesanan Makanan Kantin</p>
    </div>
</footer>

</body>
</html>