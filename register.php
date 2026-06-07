<?php

session_start();
require_once 'config/db.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'admin') {
        header("Location: dashboard_admin.php");
    } elseif ($_SESSION['role'] == 'penjual') {
        header("Location: dashboard_penjual.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim(mysqli_real_escape_string($conn, $_POST['username']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'pelanggan';
    $nama_warung = '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Semua kolom form wajib diisi!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid!";
    } elseif ($password !== $confirm_password) {
        $error = "Konfirmasi password tidak cocok!";
    } elseif (strlen($password) < 6) {
        $error = "Password minimum harus 6 karakter!";
    } else {
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_user) > 0) {
            $error = "Username sudah terdaftar! Gunakan nama lain.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $query = "INSERT INTO users (username, password, email, role, nama_warung) VALUES ('$username', '$hashed_password', '$email', '$role', NULL)";
            if (mysqli_query($conn, $query)) {
                $success = "Registrasi Berhasil! Silakan masuk.";
                header("refresh:2; url=login.php");
            } else {
                $error = "Gagal menyimpan data ke database: " . mysqli_error($conn);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Akun - Sistem Kantin</title>
    <link rel="stylesheet" href="css/style.css">

    <link rel="shortcut icon" href="ico/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="ico/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="ico/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="ico/favicon-16x16.png">
    <link rel="manifest" href="ico/site.webmanifest">   

    <script src="js/main.js" defer></script>
</head>
<body style="background-color: var(--light-bg); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px;">

<div class="auth-container">
    <div class="auth-header">
        <a href="index.php" class="logo" style="justify-content: center; margin-bottom: 12px;">
            <span>🍽️ Kantin Kita</span>
        </a>
        <h2>Buat Akun Anda</h2>
        <p style="color: #64748b; font-size: 14px; margin-top: 4px;">Gabung untuk menikmati pemesanan praktis</p>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="username">Username</label>
            <input type="text" id="username" name="username" class="form-control" placeholder="Masukkan username unik" required value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
        </div>

        <div class="form-group">
            <label for="email">Alamat Email</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="contoh@domain.com" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
        </div>


        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" class="form-control" placeholder="Struktur min. 6 karakter" required>
        </div>

        <div class="form-group">
            <label for="confirm_password">Konfirmasi Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Masukkan ulang password" required>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 10px; font-weight: 600;">Daftar Akun</button>

        <div class="form-footer">
            Sudah punya akun? <a href="login.php">Masuk di sini</a>
        </div>
    </form>
</div>

</body>
</html>
