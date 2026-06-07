<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$error_user = '';
$success_user = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_user'])) {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $role = mysqli_real_escape_string($conn, $_POST['role']);
    
    if (empty($username) || empty($email) || empty($role)) {
        $error_user = "Harap isi semua kolom form pengelolaan akun!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_user = "Alamat email tidak valid!";
    } elseif (!in_array($role, ['pelanggan', 'penjual', 'admin'])) {
        $error_user = "Role akun tidak valid!";
    } else {
        if ($_POST['action_user'] === 'add_user') {
            $password = $_POST['password'];
            if (strlen($password) < 6) {
                $error_user = "Untuk akun baru, password minimal 6 karakter!";
            } else {
                $chk = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
                if (mysqli_num_rows($chk) > 0) {
                    $error_user = "Username '$username' sudah terpakai!";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $nama_warung = ($role === 'penjual' && isset($_POST['nama_warung'])) ? "'" . mysqli_real_escape_string($conn, trim($_POST['nama_warung'])) . "'" : "NULL";
                    $add_query = "INSERT INTO users (username, password, email, role, nama_warung) VALUES ('$username', '$hashed_password', '$email', '$role', $nama_warung)";
                    if (mysqli_query($conn, $add_query)) {
                        $success_user = "Akun baru '$username' dengan role " . strtoupper($role) . " berhasil didaftarkan!";
                    } else {
                        $error_user = "Sistem gagal menambah user: " . mysqli_error($conn);
                    }
                }
            }
        } elseif ($_POST['action_user'] === 'update_user') {
            $update_id = intval($_POST['user_id']);
            
            $chk = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username' AND id != $update_id");
            if (mysqli_num_rows($chk) > 0) {
                $error_user = "Username '$username' sudah terpakai oleh akun lain!";
            } else {
                $nama_warung = ($role === 'penjual' && isset($_POST['nama_warung'])) ? "'" . mysqli_real_escape_string($conn, trim($_POST['nama_warung'])) . "'" : "NULL";
                $update_query = "UPDATE users SET username = '$username', email = '$email', role = '$role', nama_warung = $nama_warung WHERE id = $update_id";
                if (mysqli_query($conn, $update_query)) {
                    $success_user = "Data akun '$username' berhasil diperbarui!";
                    
                    if (!empty($_POST['password'])) {
                        $new_pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
                        mysqli_query($conn, "UPDATE users SET password = '$new_pass' WHERE id = $update_id");
                        $success_user .= " Serta password akun berhasil diganti.";
                    }
                } else {
                    $error_user = "Sistem gagal menyimpan perubahan akun: " . mysqli_error($conn);
                }
            }
        }
    }
}

if (isset($_GET['delete_user'])) {
    $delete_id = intval($_GET['delete_user']);
    
    if ($delete_id == $_SESSION['user_id']) {
        $error_user = "Keamanan Sistem: Anda dilarang keras menghapus akun Anda sendiri!";
    } else {
        $get_warung = mysqli_query($conn, "SELECT role, nama_warung FROM users WHERE id = $delete_id");
        $user_data = mysqli_fetch_assoc($get_warung);

        if ($user_data && $user_data['role'] === 'penjual' && !empty($user_data['nama_warung'])) {
            $nama_warung = mysqli_real_escape_string($conn, $user_data['nama_warung']);
            mysqli_query($conn, "DELETE FROM menu WHERE nama_warung = '$nama_warung'");
        }

        mysqli_query($conn, "DELETE FROM users WHERE id = $delete_id");
        $success_user = "Akun penjual beserta seluruh menunya berhasil dihapus.";
    }
}

$edit_mode = false;
$edit_data = ['id' => 0, 'username' => '', 'email' => '', 'role' => 'pelanggan', 'nama_warung' => ''];

if (isset($_GET['edit_user'])) {
    $edit_id = intval($_GET['edit_user']);
    $edit_res = mysqli_query($conn, "SELECT * FROM users WHERE id = $edit_id");
    if (mysqli_num_rows($edit_res) > 0) {
        $edit_mode = true;
        $edit_data = mysqli_fetch_assoc($edit_res);
    }
}


$total_users_res = mysqli_query($conn, "SELECT COUNT(id) as total FROM users");
$total_users = mysqli_fetch_assoc($total_users_res)['total'];

$total_menus_res = mysqli_query($conn, "SELECT COUNT(id) as total FROM menu");
$total_menus = mysqli_fetch_assoc($total_menus_res)['total'];

$total_trans_res = mysqli_query($conn, "SELECT COUNT(id) as total FROM orders WHERE status = 'selesai'");
$total_trans = mysqli_fetch_assoc($total_trans_res)['total'];

$users_query = "SELECT * FROM users ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_query);

$sales_log_query = "SELECT o.*, u.username 
                     FROM orders o 
                     LEFT JOIN users u ON o.user_id = u.id 
                     WHERE o.status = 'selesai' 
                     ORDER BY o.id DESC LIMIT 10";
$sales_log_result = mysqli_query($conn, $sales_log_query);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Sistem Kantin</title>
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
                <li><a href="dashboard_admin.php" class="active">Admin Panel</a></li>
                <li style="border-left: 1px solid var(--border); padding-left: 15px;">
                    <span style="font-size: 13px; color: var(--text-heading); font-weight: 500;">
                        Admin: <?= htmlspecialchars($_SESSION['username']) ?>!
                    </span>
                </li>
                <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px; margin-left:10px;">Keluar</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="margin-top: 40px; min-height: 80vh;">
    <!-- Welcome Title -->
    <div style="margin-bottom: 24px;">
        <h1 style="font-size: 26px; font-weight: 700; color: var(--text-heading);">Dashboard Administrator</h1>
        <p style="color: #64748b; font-size:14px; margin-top:2px;">Laporan Ringkasan Usaha Kantin, Laporan Keuangan, & Manajemen Pengguna</p>
    </div>

    <!-- METRICS GRID -->
    <div class="meta-grid">
        <div class="meta-card" style="border-left: 4px solid var(--primary);">
            <span class="title">Total Seluruh Pengguna</span>
            <span class="val"><?= $total_users ?> Orang</span>
        </div>
        <div class="meta-card" style="border-left: 4px solid var(--secondary);">
            <span class="title">Total Katalog Hidangan</span>
            <span class="val"><?= $total_menus ?> Menu</span>
        </div>
        <div class="meta-card" style="border-left: 4px solid var(--warning);">
            <span class="title">Pesanan Selesai Terjual</span>
            <span class="val"><?= $total_trans ?> Order</span>
        </div>
    </div>

    <!-- Alert notifications -->
    <?php if (!empty($success_user)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_user) ?></div>
    <?php endif; ?>
    <?php if (!empty($error_user)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_user) ?></div>
    <?php endif; ?>

    <!-- Split Columns Layout -->
    <div class="cart-grid">
        
        <!-- KOLOM KIRI (MANAGEMENT USERS) -->
        <div>
            <!-- CRUD Akun Users Panel -->
            <div class="panel">
                <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; margin-bottom:14px;">👥 Manajemen Otoritas & Kelola Pengguna (MySQL)</h3>
                
                <div class="table-responsive">
                    <table style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Alamat Email</th>
                                <th>Hak Akses / Role</th>
                                <th>Tgl Registrasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($u = mysqli_fetch_assoc($users_result)): ?>
                                    <tr id="user-row-<?= $u['id'] ?>" style="<?= ($edit_mode && $edit_data['id'] == $u['id']) ? 'background-color: #fef08a; border-left: 4px solid var(--secondary); font-weight: 600;' : '' ?>" class="transition-row">
                                        <td><b style="font-size:14px; color: var(--text-heading);"><?= htmlspecialchars($u['username']) ?></b></td>
                                        <td style="font-size:13px; color:#64748b;"><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <?php if ($u['role'] === 'admin'): ?>
                                                <span class="status-badge" style="background-color: #ffe4e6; color: #e11d48; font-size:11px;">Admin</span>
                                            <?php elseif ($u['role'] === 'penjual'): ?>
                                                <span class="status-badge status-sedang-dibuat" style="font-size:11px;">Penjual</span>
                                                <?php if (!empty($u['nama_warung'])): ?>
                                                    <span style="font-size: 11px; font-weight: 600; color: var(--primary); display: block; margin-top: 4px;">🏪 <?= htmlspecialchars($u['nama_warung']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="status-badge status-diproses" style="font-size:11px;">Pelanggan</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px; color: #94a3b8;"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                                        <td>
                                            <div class="action-links">
                                                <a href="dashboard_admin.php?edit_user=<?= $u['id'] ?>" class="action-btn action-edit" title="Sistem Edit Akun">✏️</a>
                                                <a href="dashboard_admin.php?delete_user=<?= $u['id'] ?>" class="action-btn action-delete" onclick="return confirm('Peringatan Sistem: Apakah Anda yakin ingin mendelete/menghapus akun pengguna &quot;<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>&quot; secara permanen? Seluruh data akun ini dan riwayat pesanannya tidak dapat dikembalikan!')" title="Sistem Delete Akun">🗑️</a>
                                            </div>
                                        </td>
                                    </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Laporan Riwayat Omset Log Transaksi -->
            <div class="panel">
                <h3 style="border-bottom: 2px solid var(--border); padding-bottom:10px; margin-bottom:14px;">🧾 Jurnal Akuntansi Transaksi Terakhir (Status Selesai)</h3>
                
                <?php if (mysqli_num_rows($sales_log_result) > 0): ?>
                    <div class="table-responsive">
                        <table style="width:100%; margin-bottom:0;">
                            <thead>
                                <tr>
                                    <th>ID Order</th>
                                    <th>Nama Pelanggan</th>
                                    <th>Stan Warung</th>
                                    <th>Tanggal Penjualan</th>
                                    <th style="text-align: right;">Hasil Omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = mysqli_fetch_assoc($sales_log_result)): ?>
                                    <tr>
                                        <td><b style="font-size:13px;">#<?= $log['id'] ?></b></td>
                                        <td style="font-size:13px;"><?= htmlspecialchars($log['username'] ?? 'User Dihapus') ?></td>
                                        <td><span class="status-badge" style="background-color: #f1f5f9; color: var(--primary); font-size:11px; padding: 2.5px 6px; font-weight: 600; border: 1px solid var(--border);">🏪 <?= htmlspecialchars($log['nama_warung'] ?? 'Kantin Utama') ?></span></td>
                                        <td style="font-size:12px; color:#64748b;"><?= date('d F Y, H:i', strtotime($log['tanggal_order'])) ?></td>
                                        <td style="text-align: right; font-weight:600; color: var(--primary); font-size:13px;">Rp <?= number_format($log['total_harga'], 0, ',', '.') ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #94a3b8; font-size:13px; padding: 15px 0;">Belum ada omset penjualan terekam.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- KOLOM KANAN (FORM INPUT / EDIT ACCOUNTS) -->
        <div>
            <div class="panel" style="border-top: 4px solid var(--secondary); position: sticky; top: 100px;">
                <h3 style="margin-bottom:12px; display:flex; align-items:center; gap:8px;">
                    <?= $edit_mode ? '✏️ Koreksi Akun Pengguna' : '➕ Daftarkan Akun Otoritas Baru' ?>
                </h3>
                <p style="color: #64748b; font-size: 13px; margin-bottom: 20px;">Mengatur hak akses login user (Pelanggan, Penjual Kantin, & Operator Admin) langsung terhubung sistem enkripsi database.</p>

                <form method="POST" action="dashboard_admin.php">
                    <input type="hidden" name="action_user" value="<?= $edit_mode ? 'update_user' : 'add_user' ?>">
                    <?php if ($edit_mode): ?>
                        <input type="hidden" name="user_id" value="<?= $edit_data['id'] ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="username">Username Akun</label>
                        <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username unik" required value="<?= htmlspecialchars($edit_data['username']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Alamat Email Aktif</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="contoh@mail.com" required value="<?= htmlspecialchars($edit_data['email']) ?>">
                    </div>

                    <div class="form-group">
                        <label for="role">Hak Akses Sistem</label>
                        <select id="role" name="role" class="form-control" required style="cursor: pointer;">
                            <option value="pelanggan" <?= $edit_data['role'] === 'pelanggan' ? 'selected' : '' ?>>Pelanggan / Pembeli</option>
                            <option value="penjual" <?= $edit_data['role'] === 'penjual' ? 'selected' : '' ?>>Penjual / Pemilik Kantin</option>
                            <option value="admin" <?= $edit_data['role'] === 'admin' ? 'selected' : '' ?>>Operator Admin</option>
                        </select>
                    </div>

                    <div class="form-group" id="warung-group" style="<?= $edit_data['role'] === 'penjual' ? '' : 'display: none;' ?>">
                        <label for="nama_warung">Menangani Kantin / Warung</label>
                        <?php
                        $registered_warungs = ['Warung Berkah', 'Kedai Nyaman', 'Pojok Segar', 'Cemilan Makmur', 'Kantin Utama'];
                        $warung_q = mysqli_query($conn, "SELECT DISTINCT nama_warung FROM users WHERE role = 'penjual' AND nama_warung IS NOT NULL AND nama_warung != ''");
                        if ($warung_q) {
                            while ($w_row = mysqli_fetch_assoc($warung_q)) {
                                $wn = trim($w_row['nama_warung']);
                                if (!in_array($wn, $registered_warungs) && $wn !== '') {
                                    $registered_warungs[] = $wn;
                                }
                            }
                        }
                        ?>
                        <input type="text" id="nama_warung" name="nama_warung" list="warung-list" class="form-control" placeholder="Ketik nama kantin baru atau pilih..." value="<?= htmlspecialchars($edit_data['nama_warung'] ?? '') ?>" autocomplete="off">
                        <datalist id="warung-list">
                            <?php foreach ($registered_warungs as $war_opt): ?>
                                <option value="<?= htmlspecialchars($war_opt) ?>"><?= htmlspecialchars($war_opt) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <script>
                        document.addEventListener("DOMContentLoaded", function() {
                            var roleSelect = document.getElementById('role');
                            var warungGroup = document.getElementById('warung-group');
                            function toggleWarung() {
                                if (roleSelect.value === 'penjual') {
                                    warungGroup.style.display = 'block';
                                    document.getElementById('nama_warung').required = true;
                                } else {
                                    warungGroup.style.display = 'none';
                                    document.getElementById('nama_warung').required = false;
                                }
                            }
                            roleSelect.addEventListener('change', toggleWarung);
                            toggleWarung();
                        });
                    </script>

                    <div class="form-group" style="margin-bottom: 24px;">
                        <label for="password">Password <?= $edit_mode ? '(Isi bila ingin mereset sandi baru)' : 'Akun Sandi' ?></label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="<?= $edit_mode ? 'Biarkan kosong jika tidak diubah' : 'Min. 6 Karakter' ?>" <?= $edit_mode ? '' : 'required' ?>>
                    </div>

                    <div style="display:flex; gap:10px; margin-top:20px;">
                        <button type="submit" class="btn btn-primary" style="flex:1; font-weight:600;">
                            <?= $edit_mode ? 'Simpan Akun' : 'Daftarkan Akun' ?>
                        </button>
                        <?php if ($edit_mode): ?>
                            <a href="dashboard_admin.php" class="btn btn-outline" style="font-weight:600;">Batal</a>
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

</body>
</html>
