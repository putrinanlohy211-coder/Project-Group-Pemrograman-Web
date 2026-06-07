<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['role'] !== 'pelanggan') {
    echo "<script>alert('Peringatan: Penjual/Admin tidak diperkenankan mengakses halaman keranjang belanja pelanggan!'); window.location.href='index.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $menu_id = intval($_POST['menu_id']);
        $action = $_POST['action'];

        if (isset($_POST['ajax']) && $_POST['ajax'] == 1) {
            header('Content-Type: application/json');

            if ($action === 'update_qty') {
                $new_qty = intval($_POST['qty']);
                if ($new_qty > 0) {
                    $_SESSION['cart'][$menu_id] = $new_qty;
                } else {
                    unset($_SESSION['cart'][$menu_id]);
                }
            } elseif ($action === 'remove_item') {
                unset($_SESSION['cart'][$menu_id]);
            }

            $grand_total = 0;
            $item_qty = 0;
            $item_subtotal = 0;
            $item_price = 0;

            if (!empty($_SESSION['cart'])) {
                $ids = implode(',', array_keys($_SESSION['cart']));
                $res = mysqli_query($conn, "SELECT id, harga FROM menu WHERE id IN ($ids)");
                if ($res) {
                    while ($row = mysqli_fetch_assoc($res)) {
                        $q = $_SESSION['cart'][$row['id']];
                        $sub = $row['harga'] * $q;
                        $grand_total += $sub;
                        if ($row['id'] == $menu_id) {
                            $item_qty = $q;
                            $item_price = $row['harga'];
                            $item_subtotal = $sub;
                        }
                    }
                }
            }

            $cart_count = 0;
            if (isset($_SESSION['cart'])) {
                foreach ($_SESSION['cart'] as $mid => $qty) {
                    $cart_count += $qty;
                }
            }

            echo json_encode([
                'status' => 'success',
                'menu_id' => $menu_id,
                'qty' => $item_qty,
                'item_price_formatted' => 'Rp ' . number_format($item_price, 0, ',', '.'),
                'subtotal_formatted' => 'Rp ' . number_format($item_subtotal, 0, ',', '.'),
                'grand_total_formatted' => 'Rp ' . number_format($grand_total, 0, ',', '.'),
                'cart_count' => $cart_count,
                'is_removed' => !isset($_SESSION['cart'][$menu_id])
            ]);
            exit;
        }

        if ($action === 'update_qty') {
            $new_qty = intval($_POST['qty']);
            if ($new_qty > 0) {
                $_SESSION['cart'][$menu_id] = $new_qty;
            } else {
                unset($_SESSION['cart'][$menu_id]);
            }
        } elseif ($action === 'remove_item') {
            unset($_SESSION['cart'][$menu_id]);
        }

        header("Location: keranjang.php");
        exit;
    }
}

if (!empty($_SESSION['cart'])) {
    foreach (array_keys($_SESSION['cart']) as $menu_id) {
        $menu_id = (int)$menu_id;
        $cek = mysqli_query($conn, "SELECT id FROM menu WHERE id = $menu_id");
        if (!$cek || mysqli_num_rows($cek) == 0) {
            unset($_SESSION['cart'][$menu_id]);
        }
    }
}

$cart_items = [];
$total_harga = 0;

if (!empty($_SESSION['cart'])) {
    $ids = implode(',', array_map('intval', array_keys($_SESSION['cart'])));
    $result = mysqli_query($conn, "SELECT id, nama_menu, harga, gambar, nama_warung FROM menu WHERE id IN ($ids)");
    while ($row = mysqli_fetch_assoc($result)) {
        $qty = $_SESSION['cart'][$row['id']];
        $subtotal = $row['harga'] * $qty;
        $cart_items[] = [
            'id'          => $row['id'],
            'nama_menu'   => $row['nama_menu'],
            'harga'       => $row['harga'],
            'gambar'      => $row['gambar'],
            'nama_warung' => $row['nama_warung'],
            'qty'         => $qty,
            'subtotal'    => $subtotal,
        ];
        $total_harga += $subtotal;
    }
}

$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $mid => $qty) {
        $cart_count += $qty;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keranjang Belanja - Sistem Kantin</title>
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
        <a href="index.php" class="logo"><span>🍽️ Kantin Kita</span></a>
        <nav>
            <ul class="nav-links">
                <li><a href="index.php">Katalog Menu</a></li>
                <li><a href="anggota_kelompok.php">Anggota Kelompok</a></li>
                <li><a href="keranjang.php" class="active">Keranjang <span class="badge"><?= $cart_count ?></span></a></li>
                <li><a href="dashboard_user.php">Pesanan Saya</a></li>
                <li>
                    <span style="font-size: 13px; color: var(--text-heading); font-weight: 500;">
                        Halo, <?= htmlspecialchars($_SESSION['username']) ?>!
                    </span>
                </li>
                <li><a href="logout.php" class="btn btn-outline" style="padding: 6px 12px;">Keluar</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="container" style="margin-top: 40px;">
    <h1 style="font-size: 26px; font-weight: 700; color: var(--text-heading); margin-bottom: 24px; text-align: center;">Detail Keranjang Belanja Anda</h1>

    <?php if (!empty($cart_items)): ?>
    <!-- ===== KERANJANG ADA ISI ===== -->
    <div class="cart-grid">

        <!-- Kolom Item List -->
        <div>
            <div class="panel" style="padding: 10px;">
                <div class="table-responsive">
                    <table style="width: 100%; margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Menu Makanan</th>
                                <th style="text-align: right;">Harga Satuan</th>
                                <th style="text-align: center;">Jumlah (Qty)</th>
                                <th style="text-align: right;">Subtotal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                                <tr id="cart-row-<?= $item['id'] ?>">
                                    <td data-label="Menu">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div>
                                                <b style="font-size:15px; color: var(--text-heading);"><?= htmlspecialchars($item['nama_menu']) ?></b>
                                                <?php if (!empty($item['nama_warung'])): ?>
                                                    <span style="display:block; font-size: 11px; color: var(--primary); font-weight: 600;">🏪 <?= htmlspecialchars($item['nama_warung']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td data-label="Harga" style="text-align: right;">
                                        Rp <?= number_format($item['harga'], 0, ',', '.') ?>
                                    </td>
                                    <td data-label="Qty" style="text-align: center;">
                                        <form method="POST" action="" class="qty-form"
                                            data-menu-id="<?= $item['id'] ?>"
                                            data-menu-name="<?= htmlspecialchars($item['nama_menu']) ?>"
                                            data-qty="<?= $item['qty'] ?>"
                                            style="display: inline-flex; align-items: center; gap: 5px; margin: 0;">
                                            <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="action" value="update_qty">
                                            <button type="submit" name="qty" value="<?= $item['qty'] - 1 ?>" class="btn btn-outline btn-dec" style="padding: 4px 10px; font-weight:bold; font-size:12px;">-</button>
                                            <span id="qty-val-<?= $item['id'] ?>" style="padding: 4px 10px; font-weight:600; font-size:13px;"><?= $item['qty'] ?></span>
                                            <button type="submit" name="qty" value="<?= $item['qty'] + 1 ?>" class="btn btn-outline btn-inc" style="padding: 4px 10px; font-weight:bold; font-size:12px;">+</button>
                                        </form>
                                    </td>
                                    <td data-label="Subtotal" id="subtotal-val-<?= $item['id'] ?>" style="text-align: right; font-weight: 600;">
                                        Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <form method="POST" action="" class="remove-form"
                                            data-menu-id="<?= $item['id'] ?>"
                                            data-menu-name="<?= htmlspecialchars($item['nama_menu']) ?>"
                                            style="margin: 0;">
                                            <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="action" value="remove_item">
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 11px;">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <a href="index.php" class="btn btn-outline" style="margin-top: 10px;">&larr; Lanjut Tambah Menu</a>
        </div>

        <!-- Kolom Summary Checkout -->
        <div>
            <div class="panel">
                <h3>Ringkasan Pembayaran</h3>
                <ul class="total-summary" style="margin-bottom: 20px;">
                    <li>
                        <span>Total Item</span>
                        <span id="total-qty-summary"><?= $cart_count ?> Porsi</span>
                    </li>
                    <li>
                        <span>Biaya Layanan Web</span>
                        <span style="color: var(--primary); font-weight: 600;">Rp 0 (Gratis)</span>
                    </li>
                    <li class="grand-total">
                        <span>Total Pembayaran</span>
                        <span id="cart-grand-total">Rp <?= number_format($total_harga, 0, ',', '.') ?></span>
                    </li>
                </ul>

                <form method="POST" action="checkout.php" id="checkout-main-form" style="margin: 0;">
                    <div class="form-group">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                            <label for="catatan" style="margin:0;">Catatan Tambahan (Opsional)</label>
                            <span id="char-counter-text" style="font-size:11px; font-family:monospace; color:#64748b;">0 / 300</span>
                        </div>
                        <textarea id="catatan" name="catatan" class="form-control" rows="3" placeholder="Contoh: Air mineral dingin, makanannya jangan pedas..." style="resize: none;"></textarea>
                        <p id="char-warning" style="display:none; color:var(--danger); font-size:11px; margin-top:4px; font-weight:600;">* Batas maksimum catatan adalah 300 karakter!</p>
                    </div>
                    <button type="submit" id="checkout-submit-btn" class="btn btn-primary" style="width: 100%; font-weight: 600; margin-top: 10px;">Konfirmasi & Checkout</button>
                </form>
            </div>
        </div>

    </div>
    <!-- ===== AKHIR KERANJANG ISI ===== -->

    <?php else: ?>
    <!-- ===== KERANJANG KOSONG: di luar cart-grid, tampil di tengah ===== -->
    <div style="display: flex; justify-content: center; align-items: center; min-height: 50vh;">
        <div class="panel" style="text-align: center; padding: 60px 40px; max-width: 480px; width: 100%;">
            <span style="font-size: 56px; display: block; margin-bottom: 16px;">🛒</span>
            <h3 style="font-size: 20px; margin-bottom: 8px; color: var(--text-heading);">Keranjang Belanja Kosong</h3>
            <p style="color: #64748b; font-size:14px; margin-bottom: 24px;">Kamu belum memesan menu masakan kantin apapun.</p>
            <a href="index.php" class="btn btn-primary">Lihat Katalog Menu</a>
        </div>
    </div>
    <?php endif; ?>

</main>

<footer>
    <div class="container">
        <p>&copy; <?= date('Y') ?> Kelompok UAS Pemrograman Web - Sistem Pemesanan Makanan Kantin</p>
    </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const qtyForms = document.querySelectorAll('.qty-form');
    qtyForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = e.submitter;
            if (!btn) return;

            const menuId = form.getAttribute('data-menu-id');
            const menuName = form.getAttribute('data-menu-name') || 'Item';
            let newQty = parseInt(btn.value);

            if (newQty <= 0) {
                if (!confirm('Apakah Anda yakin ingin menghapus "' + menuName + '" dari keranjang belanja?')) return;
            }

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('menu_id', menuId);
            formData.append('action', 'update_qty');
            formData.append('qty', newQty);

            fetch('keranjang.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.is_removed) {
                        const row = document.getElementById('cart-row-' + menuId);
                        if (row) {
                            row.style.transition = 'all 0.3s ease';
                            row.style.opacity = '0';
                            setTimeout(() => {
                                row.remove();
                                if (data.cart_count === 0) window.location.reload();
                            }, 300);
                        }
                    } else {
                        const qtySpan = document.getElementById('qty-val-' + menuId);
                        if (qtySpan) qtySpan.textContent = data.qty;

                        form.setAttribute('data-qty', data.qty);
                        const decBtn = form.querySelector('.btn-dec');
                        const incBtn = form.querySelector('.btn-inc');
                        if (decBtn) decBtn.value = data.qty - 1;
                        if (incBtn) incBtn.value = data.qty + 1;

                        const subtotalCell = document.getElementById('subtotal-val-' + menuId);
                        if (subtotalCell) subtotalCell.textContent = data.subtotal_formatted;
                    }

                    const grandTotalEl = document.getElementById('cart-grand-total');
                    if (grandTotalEl) grandTotalEl.textContent = data.grand_total_formatted;

                    const badgeEl = document.querySelector('header .badge');
                    if (badgeEl) badgeEl.textContent = data.cart_count;

                    const totalCountEl = document.getElementById('total-qty-summary');
                    if (totalCountEl) totalCountEl.textContent = data.cart_count + ' Porsi';
                }
            })
            .catch(err => console.error("AJAX Error: ", err));
        });
    });

    const removeForms = document.querySelectorAll('.remove-form');
    removeForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const menuId = form.querySelector('input[name="menu_id"]').value;
            const menuName = form.getAttribute('data-menu-name') || 'Item';

            if (!confirm('Apakah Anda yakin ingin menghapus "' + menuName + '" dari keranjang belanja?')) return;

            const formData = new FormData();
            formData.append('ajax', '1');
            formData.append('menu_id', menuId);
            formData.append('action', 'remove_item');

            fetch('keranjang.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const row = document.getElementById('cart-row-' + menuId);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        setTimeout(() => {
                            row.remove();
                            if (data.cart_count === 0) window.location.reload();
                        }, 300);
                    }

                    const grandTotalEl = document.getElementById('cart-grand-total');
                    if (grandTotalEl) grandTotalEl.textContent = data.grand_total_formatted;

                    const badgeEl = document.querySelector('header .badge');
                    if (badgeEl) badgeEl.textContent = data.cart_count;

                    const totalCountEl = document.getElementById('total-qty-summary');
                    if (totalCountEl) totalCountEl.textContent = data.cart_count + ' Porsi';
                }
            });
        });
    });

    const notesInput = document.getElementById('catatan');
    const charCounter = document.getElementById('char-counter-text');
    const charWarning = document.getElementById('char-warning');
    const checkoutBtn = document.getElementById('checkout-submit-btn');

    if (notesInput && charCounter) {
        notesInput.addEventListener('input', function() {
            const length = notesInput.value.length;
            charCounter.textContent = length + ' / 300';
            if (length > 300) {
                charCounter.style.color = '#ef4444';
                charCounter.style.fontWeight = 'bold';
                notesInput.style.borderColor = '#ef4444';
                if (charWarning) charWarning.style.display = 'block';
                if (checkoutBtn) { checkoutBtn.disabled = true; checkoutBtn.style.opacity = '0.5'; checkoutBtn.style.cursor = 'not-allowed'; }
            } else {
                charCounter.style.color = '#64748b';
                charCounter.style.fontWeight = 'normal';
                notesInput.style.borderColor = 'var(--border)';
                if (charWarning) charWarning.style.display = 'none';
                if (checkoutBtn) { checkoutBtn.disabled = false; checkoutBtn.style.opacity = '1'; checkoutBtn.style.cursor = 'pointer'; }
            }
        });
    }

    const checkoutForm = document.getElementById('checkout-main-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            if (notesInput && notesInput.value.length > 300) {
                e.preventDefault();
                alert('Peringatan: Catatan pesanan melebihi 300 karakter!');
                return;
            }
            if (checkoutBtn) {
                checkoutBtn.disabled = true;
                checkoutBtn.innerHTML = '🔄 Menyambungkan Ke Kasir...';
                checkoutBtn.style.backgroundColor = '#94a3b8';
            }
        });
    }
});
</script>

</body>
</html>