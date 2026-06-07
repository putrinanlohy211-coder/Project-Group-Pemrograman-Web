<?php

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan') {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';

if (isset($_SESSION['success_order'])) {
    $success_msg = $_SESSION['success_order'];
    unset($_SESSION['success_order']);
}

$orders_query = "SELECT o.*, u.nama_warung as warung_aktif
    FROM orders o
    INNER JOIN users u ON u.nama_warung = o.nama_warung AND u.role = 'penjual'
    WHERE o.user_id = $user_id
    ORDER BY o.tanggal_order DESC";
$orders_result = mysqli_query($conn, $orders_query);

$all_orders = [];
while ($order = mysqli_fetch_assoc($orders_result)) {
    $order_id = $order['id'];
    
    $detail_query = "SELECT od.*, m.nama_menu 
                     FROM order_detail od 
                     LEFT JOIN menu m ON od.menu_id = m.id 
                     WHERE od.order_id = $order_id";
    $detail_result = mysqli_query($conn, $detail_query);
    
    $items = [];
    while ($detail = mysqli_fetch_assoc($detail_result)) {
        $items[] = $detail;
    }
    
    $order['items'] = $items;
    $all_orders[] = $order;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Pesanan - Sistem Kantin</title>
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
    <div class="container navbar">
        <a href="index.php" class="logo">
            <span>🍽️ Kantin Kita</span>
        </a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Katalog Menu</a></li>
                <li><a href="anggota_kelompok.php">Anggota Kelompok</a></li>
                <li><a href="keranjang.php">Keranjang</a></li>
                <li><a href="dashboard_user.php" class="active">Pesanan Saya</a></li>
                <li style="border-left: 1px solid var(--border); padding-left: 15px;">
                    <span style="font-size: 13px; color: var(--text-heading); font-weight: 500;">
                        Halo, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </span>
                </li>
                <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; margin-left:10px;">Keluar</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="margin-top: 40px; min-height: 70vh;">
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: var(--text-heading);">Riwayat & Status Pemesanan Saya</h1>
        <p style="color: #64748b; font-size:14px; margin-top:2px;">Monitor tahapan piring saji masakan pesananmu di sini secara langsung.</p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <!-- List Pesanan Active & Selesai -->
    <?php if (!empty($all_orders)): ?>
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <?php foreach ($all_orders as $ord): ?>
                <div class="panel" style="margin-bottom: 0;">
                    <!-- Header Panel Pesanan -->
                    <div style="display:flex; justify-content: space-between; align-items:center; flex-wrap:wrap; gap:10px; border-bottom: 1px solid var(--border); padding-bottom:14px; margin-bottom:16px;">
                        <div>
                            <span style="font-size: 11px; font-weight: 700; color: white; background-color: var(--primary); padding: 1.5px 6px; border-radius: 4px; display: inline-block; margin-bottom: 4px;">🏪 <?= htmlspecialchars($ord['nama_warung'] ?? 'Kantin Utama') ?></span>
                            <div style="font-size: 13px; color: #64748b; font-weight: 500;">ORDER ID: #<?= $ord['id'] ?></div>
                            <h3 style="margin: 2px 0 0 0; font-size: 16px; color: var(--text-heading);">Pesanan - <?= date('d M Y, H:i', strtotime($ord['tanggal_order'])) ?> WIB</h3>
                        </div>
                        <div>
                            <!-- Status Switch Indicator -->
                            <?php if ($ord['status'] == 'pending'): ?>
                                <span class="status-badge status-pending">Menunggu Konfirmasi Penjual</span>
                            <?php elseif ($ord['status'] == 'diproses'): ?>
                                <span class="status-badge status-diproses">Sedang Diproses</span>
                            <?php elseif ($ord['status'] == 'sedang dibuat'): ?>
                                <span class="status-badge status-sedang-dibuat">Sedang Dimasak / Dibuat</span>
                            <?php elseif ($ord['status'] == 'selesai'): ?>
                                <span class="status-badge status-selesai">Selesai - Silakan Ambil di Kantin!</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Items Detail List -->
                    <div class="table-responsive" style="margin-bottom: 14px;">
                        <table style="width: 100%; margin-bottom: 0;">
                            <thead>
                                <tr>
                                    <th style="padding: 8px 12px;">Menu Hidangan</th>
                                    <th style="text-align: right; padding: 8px 12px; width:150px;">Harga Satuan</th>
                                    <th style="text-align: center; padding: 8px 12px; width:100px;">Jumlah</th>
                                    <th style="text-align: right; padding: 8px 12px; width:150px;">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ord['items'] as $it): ?>
                                    <tr>
                                        <td style="padding: 10px 12px;"><?= htmlspecialchars($it['nama_menu'] ?? 'Menu Dihapus Penjual') ?></td>
                                        <td style="text-align: right; padding: 10px 12px;">Rp <?= number_format($it['harga_satuan'], 0, ',', '.') ?></td>
                                        <td style="text-align: center; padding: 10px 12px;"><?= $it['jumlah'] ?> Porsi</td>
                                        <td style="text-align: right; padding: 10px 12px; font-weight: 500;">Rp <?= number_format($it['subtotal'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Transaksi -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; background-color: var(--light-bg); padding:10px 16px; border-radius: 8px; border: 1px solid var(--border);">
                        <div>
                            <span style="font-size:12px; color: #64748b; font-weight: 500; display:block;">Instruksi/Catatan:</span>
                            <span style="font-size:13px; color: var(--text-heading); font-style: italic;">
                                <?= !empty($ord['catatan']) ? '"'.htmlspecialchars($ord['catatan']).'"' : '-' ?>
                            </span>
                        </div>
                        <div style="text-align: right;">
                            <span style="font-size:12px; color: #64748b; font-weight: 500; display:block;">Total Belanja:</span>
                            <span style="font-size:18px; color: var(--text-heading); font-weight: 700;">Rp <?= number_format($ord['total_harga'], 0, ',', '.') ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="panel" style="text-align: center; padding: 60px 20px;">
            <span style="font-size: 48px; display: block; margin-bottom: 12px;">📑</span>
            <h3>Belum Ada Transaksi</h3>
            <p style="color: #64748b; font-size:14px; margin-bottom: 20px;">Kamu belum pernah memesan makanan apapun di platform ini.</p>
            <a href="index.php" class="btn btn-primary">Pesan Makanan Sekarang</a>
        </div>
    <?php endif; ?>
</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> Kelompok UAS Pemrograman Web - Sistem Pemesanan Makanan Kantin</p>
    </div>
</footer>

</body>
</html>