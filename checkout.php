<?php

session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'pelanggan' || empty($_SESSION['cart'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$catatan = isset($_POST['catatan']) ? mysqli_real_escape_string($conn, trim($_POST['catatan'])) : '';

$ids = implode(',', array_keys($_SESSION['cart']));
$query = "SELECT id, harga, nama_warung FROM menu WHERE id IN ($ids)";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    echo "<script>alert('Gagal mengambil data menu makanan!'); window.location.href='keranjang.php';</script>";
    exit;
}

$warung_items = [];
while ($row = mysqli_fetch_assoc($result)) {
    $menu_id = $row['id'];
    $qty = $_SESSION['cart'][$menu_id];
    $harga = $row['harga'];
    $warung = !empty($row['nama_warung']) ? $row['nama_warung'] : 'Kantin Utama';
    $subtotal = $harga * $qty;
    
    if (!isset($warung_items[$warung])) {
        $warung_items[$warung] = [
            'total_harga' => 0,
            'items' => []
        ];
    }
    
    $warung_items[$warung]['total_harga'] += $subtotal;
    $warung_items[$warung]['items'][] = [
        'menu_id' => $menu_id,
        'jumlah' => $qty,
        'harga_satuan' => $harga,
        'subtotal' => $subtotal
    ];
}

$any_success = false;
$failed_warungs = [];

foreach ($warung_items as $warung_name => $data) {
    $total_harga = $data['total_harga'];
    $items_to_insert = $data['items'];
    
    $escaped_warung = mysqli_real_escape_string($conn, $warung_name);
    $insert_order_query = "INSERT INTO orders (user_id, total_harga, status, catatan, nama_warung) 
                           VALUES ($user_id, $total_harga, 'pending', '$catatan', '$escaped_warung')";
    
    if (mysqli_query($conn, $insert_order_query)) {
        $order_id = mysqli_insert_id($conn);
        $success_flag = true;
        
        foreach ($items_to_insert as $item) {
            $m_id = $item['menu_id'];
            $qty = $item['jumlah'];
            $price = $item['harga_satuan'];
            $sub = $item['subtotal'];
            
            $insert_detail = "INSERT INTO order_detail (order_id, menu_id, jumlah, harga_satuan, subtotal) 
                              VALUES ($order_id, $m_id, $qty, $price, $sub)";
            if (!mysqli_query($conn, $insert_detail)) {
                $success_flag = false;
                break;
            }
        }
        
        if ($success_flag) {
            $any_success = true;
        } else {
            mysqli_query($conn, "DELETE FROM orders WHERE id = $order_id");
            $failed_warungs[] = $warung_name;
        }
    } else {
        $failed_warungs[] = $warung_name;
    }
}

if ($any_success) {
    $_SESSION['cart'] = [];
    
    if (empty($failed_warungs)) {
        $_SESSION['success_order'] = "Pemesanan berhasil! Pesanan Anda telah dipisahkan secara otomatis dan dikirim ke masing-masing pemilik warung kantin.";
    } else {
        $failed_list = implode(', ', $failed_warungs);
        $_SESSION['success_order'] = "Sebagian pesanan berhasil dipisah & dikirim, namun pesanan ke: " . htmlspecialchars($failed_list) . " gagal dibuat.";
    }
    header("Location: dashboard_user.php");
    exit;
} else {
    echo "<script>alert('Gagal memproses transaksi checkout. Silakan coba lagi!'); window.location.href='keranjang.php';</script>";
    exit;
}
?>