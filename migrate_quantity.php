<?php
require_once 'includes/db.php';

try {
    $pdo->exec("ALTER TABLE shipments ADD COLUMN quantity INT DEFAULT 1 AFTER goods_type");
    echo "Sukses: Kolom 'quantity' berhasil ditambahkan ke tabel shipments.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Info: Kolom 'quantity' sudah ada.";
    } else {
        echo "Gagal: " . $e->getMessage();
    }
}
?>
