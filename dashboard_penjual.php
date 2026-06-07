<?php

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'penjual') {
    header("Location: login.php");
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_query = mysqli_query($conn, "SELECT nama_warung FROM users WHERE id = $user_id");
$user_row = mysqli_fetch_assoc($user_query);
$seller_warung = !empty($user_row['nama_warung']) ? $user_row['nama_warung'] : 'Kantin Utama';

$active_warung = $seller_warung;
$_SESSION['nama_warung'] = $seller_warung;

$error_menu = '';
$success_menu = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_menu'])) {
    $nama_menu = trim(mysqli_real_escape_string($conn, $_POST['nama_menu']));
    $deskripsi = trim(mysqli_real_escape_string($conn, $_POST['deskripsi']));
    $harga = intval($_POST['harga']);
    $category_id = intval($_POST['kategori_id']);
    $ketersediaan = isset($_POST['ketersediaan']) ? intval($_POST['ketersediaan']) : 1;
    $nama_warung = $seller_warung; 
    
    if (empty($nama_menu) || strlen($nama_menu) < 3 || $harga <= 0 || $category_id <= 0) {
        $error_menu = "Validasi Gagal: Nama menu minimal 3 karakter, dan harga harus valid!";
    } else {
        $gambar = 'default.jpg';
        
        if ($_POST['action_menu'] === 'update_menu' && isset($_POST['menu_id'])) {
            $check_id = intval($_POST['menu_id']);
            $old_g_res = mysqli_query($conn, "SELECT gambar FROM menu WHERE id = $check_id");
            if ($old_g_row = mysqli_fetch_assoc($old_g_res)) {
                $gambar = $old_g_row['gambar'];
            }
        }

        if (!empty($_POST['gambar_url'])) {
            $gambar = trim(mysqli_real_escape_string($conn, $_POST['gambar_url']));
        }

        if (isset($_FILES['gambar_file']) && $_FILES['gambar_file']['error'] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['gambar_file']['name'];
            $file_tmp = $_FILES['gambar_file']['tmp_name'];
            $file_size = $_FILES['gambar_file']['size'];
            
            $file_parts = explode('.', $file_name);
            $file_ext = strtolower(end($file_parts));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_exts)) {
                if ($file_size <= 2 * 1024 * 1024) {
                    $upload_folder = __DIR__ . '/uploads/';
                    if (!file_exists($upload_folder)) {
                        mkdir($upload_folder, 0777, true);
                    }
                    
                    $new_file_name = 'menu_' . uniqid() . '_' . time() . '.' . $file_ext;
                    if (move_uploaded_file($file_tmp, $upload_folder . $new_file_name)) {
                        $gambar = $new_file_name;
                    } else {
                        $error_menu = "Gagal memindahkan file unggahan ke folder tujuan server.";
                    }
                } else {
                    $error_menu = "Sistem Menolak: Ukuran gambar melebihi batas resolusi maksimal (2MB).";
                }
            } else {
                $error_menu = "Sistem Menolak: Format file tidak valid! Harap gunakan ekstensi JPG, PNG, GIF, atau WEBP.";
            }
        }

        if (empty($error_menu)) {
            if ($_POST['action_menu'] === 'add_menu') {
                $add_query = "INSERT INTO menu (nama_menu, deskripsi, harga, ketersediaan, kategori_id, gambar, nama_warung) 
                              VALUES ('$nama_menu', '$deskripsi', $harga, $ketersediaan, $category_id, '$gambar', '$nama_warung')";
                if (mysqli_query($conn, $add_query)) {
                    $success_menu = "Menu '$nama_menu' berhasil ditambahkan ke katalog Anda!";
                } else {
                    $error_menu = "Gagal menambah menu ke sistem: " . mysqli_error($conn);
                }
            } elseif ($_POST['action_menu'] === 'update_menu') {
                $update_id = intval($_POST['menu_id']);
                
                $check_owner = mysqli_query($conn, "SELECT nama_warung FROM menu WHERE id = $update_id");
                $menu_owner = mysqli_fetch_assoc($check_owner);
                
                if (!$menu_owner || $menu_owner['nama_warung'] !== $seller_warung) {
                    $error_menu = "Akses Ditolak: Anda tidak memiliki otoritas mengubah menu ini!";
                } else {
                    $update_query = "UPDATE menu SET 
                                        nama_menu = '$nama_menu', 
                                        deskripsi = '$deskripsi', 
                                        harga = $harga, 
                                        ketersediaan = $ketersediaan, 
                                        kategori_id = $category_id,
                                        gambar = '$gambar',
                                        nama_warung = '$nama_warung'
                                     WHERE id = $update_id";
                    if (mysqli_query($conn, $update_query)) {
                        $success_menu = "Menu '$nama_menu' berhasil diperbarui!";
                    } else {
                        $error_menu = "Gagal memperbarui menu: " . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

if (isset($_GET['delete_menu'])) {
    $delete_id = intval($_GET['delete_menu']);
    
    $check_owner = mysqli_query($conn, "SELECT nama_warung FROM menu WHERE id = $delete_id");
    $menu_owner = mysqli_fetch_assoc($check_owner);
    
    if (!$menu_owner || $menu_owner['nama_warung'] !== $seller_warung) {
        $error_menu = "Akses Ditolak: Anda tidak memiliki otoritas menghapus menu ini!";
    } else {
        $check_orders = mysqli_query($conn, "SELECT id FROM order_detail WHERE menu_id = $delete_id");
        if (mysqli_num_rows($check_orders) > 0) {
            mysqli_query($conn, "UPDATE menu SET ketersediaan = 0 WHERE id = $delete_id");
            $success_menu = "Menu memiliki riwayat pemesanan. Untuk keamanan data, ketersediaan diubah menjadi 'Habis'!";
        } else {
            mysqli_query($conn, "DELETE FROM menu WHERE id = $delete_id");
            $success_menu = "Menu berhasil dihapus sepenuhnya!";
        }
    }
}

$edit_mode = false;
$edit_data = ['id' => 0, 'nama_menu' => '', 'deskripsi' => '', 'harga' => 0, 'ketersediaan' => 1, 'kategori_id' => 0, 'nama_warung' => ''];

if (isset($_GET['edit_menu'])) {
    $edit_id = intval($_GET['edit_menu']);
    $edit_res = mysqli_query($conn, "SELECT * FROM menu WHERE id = $edit_id AND nama_warung = '$seller_warung'");
    if (mysqli_num_rows($edit_res) > 0) {
        $edit_mode = true;
        $edit_data = mysqli_fetch_assoc($edit_res);
    } else {
        $error_menu = "Akses Ditolak: Anda tidak memiliki otoritas mengedit menu ini!";
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_order'])) {
    $order_id = intval($_POST['order_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $check_ord_owner = mysqli_query($conn, "SELECT nama_warung FROM orders WHERE id = $order_id");
    $ord_owner = mysqli_fetch_assoc($check_ord_owner);
    
    if (!$ord_owner || $ord_owner['nama_warung'] !== $seller_warung) {
        $error_menu = "Akses Ditolak: Anda tidak memiliki otoritas mengubah status pesanan ini!";
    } elseif (in_array($new_status, ['pending', 'diproses', 'sedang dibuat', 'selesai'])) {
        $update_status_query = "UPDATE orders SET status = '$new_status' WHERE id = $order_id";
        if (mysqli_query($conn, $update_status_query)) {
            $success_menu = "Status pemesanan #$order_id berhasil diubah menjadi: " . strtoupper($new_status);
        }
    }
}


$categories_result = mysqli_query($conn, "SELECT * FROM kategori");
$kategori_list = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    $kategori_list[] = $cat;
}

$warung_list_query = "SELECT DISTINCT nama_warung FROM menu WHERE nama_warung IS NOT NULL AND nama_warung != '' ORDER BY nama_warung ASC";
$warung_list_result = mysqli_query($conn, $warung_list_query);
$all_warungs = [];
while ($row = mysqli_fetch_assoc($warung_list_result)) {
    $all_warungs[] = $row['nama_warung'];
}

$menu_query = "SELECT m.*, k.nama_kategori FROM menu m LEFT JOIN kategori k ON m.kategori_id = k.id";
if ($active_warung !== 'Semua Warung') {
    $escaped_aw = mysqli_real_escape_string($conn, $active_warung);
    $menu_query .= " WHERE m.nama_warung = '$escaped_aw'";
}
$menu_query .= " ORDER BY m.id DESC";
$all_menus_result = mysqli_query($conn, $menu_query);

$orders_query = "SELECT o.*, u.username FROM orders o LEFT JOIN users u ON o.user_id = u.id";
if ($active_warung !== 'Semua Warung') {
    $escaped_aw = mysqli_real_escape_string($conn, $active_warung);
    $orders_query .= " WHERE o.nama_warung = '$escaped_aw'";
}
$orders_query .= " ORDER BY o.tanggal_order DESC";
$orders_result = mysqli_query($conn, $orders_query);

$pending_orders = [];
$active_orders = [];
while ($ord = mysqli_fetch_assoc($orders_result)) {
    $ord_id = $ord['id'];
    $items_res = mysqli_query($conn, "SELECT od.*, m.nama_menu FROM order_detail od LEFT JOIN menu m ON od.menu_id = m.id WHERE od.order_id = $ord_id");
    $items = [];
    while ($it = mysqli_fetch_assoc($items_res)) {
        $items[] = $it;
    }
    $ord['items'] = $items;
    
    if ($ord['status'] === 'selesai') {
        $active_orders[] = $ord; 
    } else {
        $pending_orders[] = $ord; 
    }
}

$escaped_warung = mysqli_real_escape_string($conn, $seller_warung);
$omset_res = mysqli_query($conn, "SELECT COUNT(id) as total_order, SUM(total_harga) as total_omset FROM orders WHERE nama_warung = '$escaped_warung' AND status = 'selesai'");
$omset_data = mysqli_fetch_assoc($omset_res);
$total_order_selesai = $omset_data['total_order'] ?? 0;
$total_omset = $omset_data['total_omset'] ?? 0;

$rekap_query = "SELECT m.nama_menu, m.harga, 
                       SUM(od.jumlah) as total_terjual, 
                       SUM(od.subtotal) as total_pendapatan
                FROM order_detail od
                LEFT JOIN menu m ON od.menu_id = m.id
                LEFT JOIN orders o ON od.order_id = o.id
                WHERE o.nama_warung = '$escaped_warung' AND o.status = 'selesai'
                GROUP BY od.menu_id, m.nama_menu, m.harga
                ORDER BY total_pendapatan DESC";
$rekap_result = mysqli_query($conn, $rekap_query);

$transaksi_query = "SELECT o.id, o.tanggal_order, o.total_harga, o.catatan, u.username
                    FROM orders o
                    LEFT JOIN users u ON o.user_id = u.id
                    WHERE o.nama_warung = '$escaped_warung' AND o.status = 'selesai'
                    ORDER BY o.tanggal_order DESC LIMIT 10";
$transaksi_result = mysqli_query($conn, $transaksi_query);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Kantin Penjual - Sistem Kantin</title>
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
                <li><a href="dashboard_penjual.php" class="active">Kantin Panel</a></li>
                <li style="border-left: 1px solid var(--border); padding-left: 15px;">
                    <span style="font-size: 13px; color: var(--text-heading); font-weight: 500;">
                        Kantin: <?= htmlspecialchars($_SESSION['username']) ?>!
                    </span>
                </li>
                <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; margin-left:10px;">Keluar</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="margin-top: 40px; min-height: 80vh;">
    <!-- Welcome Panel -->
    <div style="margin-bottom: 24px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <h1 style="font-size: 26px; font-weight: 700; color: var(--text-heading);">Dashboard Pemilik Kantin</h1>
            <p style="color: #64748b; font-size:14px; margin-top:2px;">Atur ketersediaan menu makanan & proses pesanan pelanggan di sini.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
             <div class="status-badge status-diproses" style="font-size:13px; padding:6px 12px; font-weight:600;">
                 Dalam Antrean: <?= count($pending_orders) ?> Pesanan
            </div>
        </div>

    <!-- Info Warung Panel (Isolasi Data Penjual) -->
    <div style="background-color: var(--light-bg); border: 1px solid var(--border); border-radius: var(--radius); padding: 14px 20px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 24px;">🏪</span>
            <div>
                <b style="font-size: 15px; color: var(--text-heading); display: block;">Stan Kantin Anda: <span style="color: var(--primary); font-size:16px;"><?= htmlspecialchars($seller_warung) ?></span></b>
                <span style="font-size: 12px; color: #64748b;">Menampilkan menu masakan & detail pesanan khusus dari pelanggan untuk stan Anda sendiri.</span>
            </div>
        </div>
        </div>
    </div>

    <!-- Kartu Omset Penjual -->
    <div class="meta-grid" style="margin-bottom: 24px;">
        <div class="meta-card" style="border-left: 4px solid var(--secondary);">
            <span class="title">Total Pesanan Selesai</span>
            <span class="val"><?= $total_order_selesai ?> Order</span>
        </div>
        <div class="meta-card" style="border-left: 4px solid #f43f5e; background-color: rgba(244,63,94,0.02);">
            <span class="title">Total Pemasukan / Omset</span>
            <span class="val" style="color: #f43f5e;">Rp <?= number_format($total_omset, 0, ',', '.') ?></span>
        </div>
        <div class="meta-card" style="border-left: 4px solid var(--primary);">
            <span class="title">Total Menu Terpasang</span>
            <span class="val"><?= mysqli_num_rows(mysqli_query($conn, "SELECT id FROM menu WHERE nama_warung = '$escaped_warung'")) ?> Menu</span>
        </div>
    </div>

    <!-- Alert status -->
    <?php if (!empty($success_menu)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_menu) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_menu)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_menu) ?></div>
    <?php endif; ?>

    <!-- Grid Pembagian Konten Utama -->
    <div class="cart-grid">
        
        <!-- KOLOM 1: PESANAN MASUK (KIRI) -->
        <div>
            <!-- List Antrean Pesanan -->
            <div class="panel">
                <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; display:flex; align-items:center; gap:8px;">🛎️ Antrean Pesanan Masuk (Butuh Tindakan)</h3>
                
                <?php if (!empty($pending_orders)): ?>
                    <div style="display: flex; flex-direction: column; gap: 18px; margin-top:14px;">
                        <?php foreach ($pending_orders as $o): ?>
                            <div style="border: 1px solid var(--border); padding: 16px; border-radius: var(--radius); background-color: var(--light-bg);">
                                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom: 10px;">
                                    <div>
                                        <span style="font-size: 12px; color:#64748b; font-weight:600;">ID ORDER: #<?= $o['id'] ?></span>
                                        <b style="display:block; font-size:14px; color: var(--text-heading);"><?= htmlspecialchars($o['username']) ?> &bull; <span style="font-size:12px; color:#64748b; font-weight:normal;"><?= date('H:i', strtotime($o['tanggal_order'])) ?> WIB</span></b>
                                    </div>
                                    
                                    <!-- STATUS CHANGER FORM -->
                                    <form method="POST" action="" style="display:flex; gap:5px; align-items:center; margin:0;">
                                        <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                        <input type="hidden" name="action_order" value="change_status">
                                        <select name="status" class="form-control" style="font-size: 12px; padding: 4px 10px; width:140px; cursor:pointer;" onchange="if (confirm('Apakah Anda yakin ingin mengganti status pesanan #<?= $o['id'] ?> menjadi: ' + this.value.toUpperCase() + '?')) { this.form.submit(); } else { window.location.reload(); }">
                                            <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                            <option value="diproses" <?= $o['status'] === 'diproses' ? 'selected' : '' ?>>Diproses</option>
                                            <option value="sedang dibuat" <?= $o['status'] === 'sedang dibuat' ? 'selected' : '' ?>>Dimasak</option>
                                            <option value="selesai" <?= $o['status'] === 'selesai' ? 'selected' : '' ?>>Selesai</option>
                                        </select>
                                    </form>
                                </div>

                                <!-- Ordered Items details -->
                                <div style="font-size:13px; color: var(--text-heading); background-color: white; border-radius: 8px; border:1px solid var(--border); padding:10px; margin-bottom:10px;">
                                    <ul style="list-style:none; margin-left:5px;">
                                        <?php foreach ($o['items'] as $item): ?>
                                            <li style="display:flex; justify-content:space-between; border-bottom:1px dashed #f1f5f9; padding:4px 0;">
                                                <span>🍔 <b><?= $item['jumlah'] ?>x</b> <?= htmlspecialchars($item['nama_menu'] ?? 'Menu Dihapus') ?></span>
                                                <span style="color:#64748b;">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div style="font-size:11px; color:#94a3b8; margin-top:8px; font-style:italic;">
                                        Catatan: <?= !empty($o['catatan']) ? '"'.htmlspecialchars($o['catatan']).'"' : 'tidak ada' ?>
                                    </div>
                                </div>

                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <span style="font-size:12px; color:#64748b;">Total Pembayaran:</span>
                                    <b style="font-size:15px; color: var(--primary);">Rp <?= number_format($o['total_harga'], 0, ',', '.') ?></b>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #64748b; font-size:14px; padding: 30px 0;">Tidak ada pesanan masuk saat ini.</p>
                <?php endif; ?>
            </div>

            <!-- List Menu Catalog (Kantin CRUD Table) -->
            <div class="panel">
                <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; margin-bottom:14px;">🗂️ Manajemen Menu Makanan & Minuman (CRUD)</h3>
                
                <div class="table-responsive">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Nama Menu</th>
                                <th>Kategori</th>
                                <th style="text-align: right;">Harga</th>
                                <th style="text-align: center;">Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($all_menus_result) > 0): ?>
                                <?php while ($m = mysqli_fetch_assoc($all_menus_result)): ?>
                                    <tr>
                                        <td>
                                            <b style="font-size:14px; color: var(--text-heading);"><?= htmlspecialchars($m['nama_menu']) ?></b>
                                            <span style="display:block; font-size:11px; color:#64748b; font-weight:normal; overflow: hidden; text-overflow:ellipsis; max-width:180px; white-space:nowrap;"><?= htmlspecialchars($m['deskripsi']) ?></span>
                                        </td>
                                        <td style="font-size:13px;"><?= htmlspecialchars($m['nama_kategori'] ?? 'Lain-lain') ?></td>
                                        <td style="text-align: right; font-weight: 600; font-size:13px;">Rp <?= number_format($m['harga'], 0, ',', '.') ?></td>
                                        <td style="text-align: center;">
                                            <?php if ($m['ketersediaan'] == 1): ?>
                                                <span class="status-badge status-selesai" style="font-size:11px; padding:2px 6px;">Tersedia</span>
                                            <?php else: ?>
                                                <span class="status-badge status-pending" style="font-size:11px; padding:2px 6px;">Habis</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-links">
                                                <a href="dashboard_penjual.php?edit_menu=<?= $m['id'] ?>" class="action-btn action-edit" title="Edit Menu">✏️</a>
                                                <a href="dashboard_penjual.php?delete_menu=<?= $m['id'] ?>" class="action-btn action-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus &quot;<?= htmlspecialchars($m['nama_menu'], ENT_QUOTES) ?>&quot; dari katalog secara permanen?')" title="Hapus Menu">🗑️</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; color:#94a3b8;">Belum ada menu di database.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- REKAP PENJUALAN PER MENU -->
<div class="panel" style="margin-top: 20px;">
    <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        📊 Rekap Penjualan Per Menu
    </h3>

    <?php
    $has_rekap = false;
    if ($rekap_result && mysqli_num_rows($rekap_result) > 0):
        $has_rekap = true;
    ?>
    <div class="table-responsive">
        <table style="width:100%; margin-bottom:0;">
            <thead>
                <tr>
                    <th>Nama Menu</th>
                    <th style="text-align:center;">Harga Satuan</th>
                    <th style="text-align:center;">Terjual (Porsi)</th>
                    <th style="text-align:right;">Total Pendapatan</th>
                    <th style="text-align:center;">Kontribusi</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                mysqli_data_seek($rekap_result, 0);
                $grand = 0;
                while ($r = mysqli_fetch_assoc($rekap_result)) $grand += $r['total_pendapatan'];
                mysqli_data_seek($rekap_result, 0);

                while ($r = mysqli_fetch_assoc($rekap_result)):
                    $persen = $grand > 0 ? round(($r['total_pendapatan'] / $grand) * 100) : 0;
                ?>
                <tr>
                    <td>
                        <b style="font-size:14px; color:var(--text-heading);"><?= htmlspecialchars($r['nama_menu'] ?? 'Menu Dihapus') ?></b>
                    </td>
                    <td style="text-align:center; font-size:13px; color:#64748b;">
                        Rp <?= number_format($r['harga'], 0, ',', '.') ?>
                    </td>
                    <td style="text-align:center;">
                        <span class="status-badge status-selesai" style="font-size:12px; padding:3px 8px;">
                            <?= $r['total_terjual'] ?> Porsi
                        </span>
                    </td>
                    <td style="text-align:right; font-weight:600; color:var(--primary); font-size:13px;">
                        Rp <?= number_format($r['total_pendapatan'], 0, ',', '.') ?>
                    </td>
                    <td style="text-align:center; min-width:100px;">
                        <div style="background:#f1f5f9; border-radius:999px; height:8px; width:100%; overflow:hidden;">
                            <div style="background:var(--primary); height:100%; width:<?= $persen ?>%; border-radius:999px; transition:width 0.4s;"></div>
                        </div>
                        <span style="font-size:11px; color:#64748b; margin-top:3px; display:block;"><?= $persen ?>%</span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background:#f8fafc; border-top:2px solid var(--border);">
                    <td colspan="2" style="font-weight:700; font-size:13px; padding:10px 8px; color:var(--text-heading);">TOTAL KESELURUHAN</td>
                    <td style="text-align:center; font-weight:700; font-size:13px;">
                        <?php
                        mysqli_data_seek($rekap_result, 0);
                        $total_porsi = 0;
                        while ($r = mysqli_fetch_assoc($rekap_result)) $total_porsi += $r['total_terjual'];
                        echo $total_porsi . ' Porsi';
                        ?>
                    </td>
                    <td style="text-align:right; font-weight:700; font-size:14px; color:#f43f5e;">
                        Rp <?= number_format($grand, 0, ',', '.') ?>
                    </td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
        <p style="text-align:center; color:#94a3b8; font-size:14px; padding:20px 0;">Belum ada data penjualan yang selesai.</p>
    <?php endif; ?>
</div>

<!-- RIWAYAT 10 TRANSAKSI TERAKHIR -->
<div class="panel" style="margin-top: 20px;">
    <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; margin-bottom:14px; display:flex; align-items:center; gap:8px;">
        🧾 Riwayat Transaksi Terakhir (10 Terbaru)
    </h3>

    <?php if ($transaksi_result && mysqli_num_rows($transaksi_result) > 0): ?>
    <div class="table-responsive">
        <table style="width:100%; margin-bottom:0;">
            <thead>
                <tr>
                    <th>ID Order</th>
                    <th>Pelanggan</th>
                    <th>Tanggal</th>
                    <th>Catatan</th>
                    <th style="text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = mysqli_fetch_assoc($transaksi_result)): ?>
                <tr>
                    <td><b style="font-size:13px;">#<?= $t['id'] ?></b></td>
                    <td style="font-size:13px;"><?= htmlspecialchars($t['username'] ?? 'User Dihapus') ?></td>
                    <td style="font-size:12px; color:#64748b;"><?= date('d M Y, H:i', strtotime($t['tanggal_order'])) ?> WIB</td>
                    <td style="font-size:12px; color:#94a3b8; font-style:italic; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                        <?= !empty($t['catatan']) ? '"'.htmlspecialchars($t['catatan']).'"' : '-' ?>
                    </td>
                    <td style="text-align:right; font-weight:600; color:var(--primary); font-size:13px;">
                        Rp <?= number_format($t['total_harga'], 0, ',', '.') ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p style="text-align:center; color:#94a3b8; font-size:14px; padding:20px 0;">Belum ada riwayat transaksi selesai.</p>
    <?php endif; ?>
</div>
        </div>

        <!-- KOLOM 2: FORM TAMBAH/EDIT MENU (KANAN) -->
        <div>
            <div class="panel" style="border-top: 4px solid var(--secondary); position: sticky; top: 100px;">
                <h3 style="margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                    <?= $edit_mode ? '✏️ Edit Menu Hidangan' : '➕ Tambah Menu Baru' ?>
                </h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Kelola data makanan, minuman, dan cemilan kantin secara dinamis disimpan ke database mysql.</p>

                <form method="POST" action="dashboard_penjual.php" enctype="multipart/form-data">
                    <input type="hidden" name="action_menu" value="<?= $edit_mode ? 'update_menu' : 'add_menu' ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="menu_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nama_menu">Nama Menu Hidangan</label>
                        <input type="text" id="nama_menu" name="nama_menu" class="form-control" placeholder="Contoh: Nasi Goreng Spesial" required minlength="3" value="<?= htmlspecialchars($edit_data['nama_menu']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="nama_warung_field">Nama Warung (Stan Kantin)</label>
                        <input type="text" id="nama_warung_field" class="form-control" value="<?= htmlspecialchars($seller_warung) ?>" readonly disabled style="background-color: #e2e8f0; font-weight: 600; cursor: not-allowed; color: #475569;">
                    </div>

                    <div class="form-group">
                        <label for="kategori_id">Menu Kategori</label>
                        <select id="kategori_id" name="kategori_id" class="form-control" required style="cursor: pointer;">
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $kl): ?>
                                <option value="<?= $kl['id'] ?>" <?= $edit_data['kategori_id'] == $kl['id'] ? 'selected' : '' ?>><?= htmlspecialchars($kl['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="harga">Harga (Rupiah)</label>
                        <input type="number" id="harga" name="harga" class="form-control" placeholder="Nilai angka saja, e.g: 15000" min="500" required value="<?= $edit_data['harga'] > 0 ? $edit_data['harga'] : '' ?>">
                        <p id="harga-preview" style="display:none; font-size:12px; color:var(--primary); font-weight:bold; margin-top:4px; font-family:monospace;"></p>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi">Deskripsi Detail Menu</label>
                        <textarea id="deskripsi" name="deskripsi" class="form-control" rows="3" placeholder="Isi penjelasan isi, toping, dll dari menu hidangan..." style="resize:none;"><?= htmlspecialchars($edit_data['deskripsi']) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="ketersediaan">Status Ketersediaan</label>
                        <select id="ketersediaan" name="ketersediaan" class="form-control" required style="cursor: pointer;">
                            <option value="1" <?= $edit_data['ketersediaan'] == 1 ? 'selected' : '' ?>>Tersedia (Ready Stock)</option>
                            <option value="0" <?= $edit_data['ketersediaan'] == 0 ? 'selected' : '' ?>>Habis (Out of Stock)</option>
                        </select>
                    </div>

                    <div class="form-group" style="margin-bottom: 20px; padding: 15px; border: 1px dashed var(--border); border-radius: var(--radius); background: #f8fafc;">
                        <label style="margin-bottom: 10px; font-weight: 600; display: block; color: var(--text-heading);">🖼️ Foto Menu Hidangan</label>
                        
                        <div style="margin-bottom: 12px;">
                            <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Metode A: Unggah Berkas Foto</span>
                            <input type="file" id="gambar_file" name="gambar_file" accept="image/*" class="form-control" style="padding: 4px; background: white; border: 1px solid var(--border);">
                        </div>
                        
                        <div>
                            <span style="font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;">Metode B: Tautkan Tautan/Link Gambar</span>
                            <input type="url" id="gambar_url" name="gambar_url" class="form-control" placeholder="Hubungkan link gambar, e.g. https://domain.com/foto.jpg" value="<?= $edit_mode && (strpos($edit_data['gambar'], 'http') === 0) ? htmlspecialchars($edit_data['gambar']) : '' ?>">
                        </div>
                        
                        <span style="font-size:11px; color:#94a3b8; display:block; margin-top:6px; line-height:1.4;">Kosongkan bila ingin menggunakan preset gambar default otomatis sistem.</span>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1; font-weight:600;">
                            <?= $edit_mode ? 'Simpan Perubahan' : 'Publish Menu' ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="dashboard_penjual.php" class="btn btn-outline" style="font-weight:600;">Batal</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

    </div>
</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> Kelompok UAS Pemrograman Web - Sistem Pemesanan Makanan Kantin</p>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const hargaInput = document.getElementById('harga');
    const hargaPreview = document.getElementById('harga-preview');
    if (hargaInput && hargaPreview) {
        const formatRupiah = (val) => {
            return "= Rp " + parseInt(val).toLocaleString('id-ID');
        };
        const updatePreview = () => {
            const val = hargaInput.value;
            if (val && parseInt(val) > 0) {
                hargaPreview.textContent = formatRupiah(val);
                hargaPreview.style.display = 'block';
            } else {
                hargaPreview.style.display = 'none';
                hargaPreview.textContent = '';
            }
        };
        hargaInput.addEventListener('input', updatePreview);
        updatePreview();
    }
});
</script>

</body>
</html>