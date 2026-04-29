<?php
/**
 * Script Migrasi Data: MySQL ke SQLite
 * Jalankan file ini sekali saja melalui browser: http://localhost/DMS-ManajemenPengetahuan/migrate_to_sqlite.php
 */

// Konfigurasi MySQL (Sesuaikan jika berbeda)
$mysql_host = 'localhost';
$mysql_db   = 'dms_logistik';
$mysql_user = 'root';
$mysql_pass = '';

// File SQLite
$sqlite_file = __DIR__ . '/database.sqlite';

try {
    // 1. Koneksi ke MySQL
    $mysql_pdo = new PDO("mysql:host=$mysql_host;dbname=$mysql_db", $mysql_user, $mysql_pass);
    $mysql_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Terhubung ke MySQL.<br>";

    // 2. Koneksi ke SQLite
    $sqlite_pdo = new PDO("sqlite:$sqlite_file");
    $sqlite_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Terhubung ke SQLite.<br><br>";

    // Matikan Foreign Key Check sementara di SQLite biar lancar importnya
    $sqlite_pdo->exec("PRAGMA foreign_keys = OFF;");

    // Daftar tabel yang akan dimigrasi (Urutan penting untuk relasi)
    $tables = [
        'users',
        'routes',
        'train_schedules',
        'shipments',
        'shipment_items',
        'invoices',
        'documents'
    ];

    foreach ($tables as $table) {
        echo "Mengimpor tabel: <strong>$table</strong>... ";

        // Ambil data dari MySQL
        $stmt = $mysql_pdo->query("SELECT * FROM $table");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            // Bersihkan tabel di SQLite sebelum isi (opsional)
            $sqlite_pdo->exec("DELETE FROM $table");

            // Siapkan query insert
            $columns = array_keys($rows[0]);
            $colString = implode(', ', $columns);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            
            $insertSql = "INSERT INTO $table ($colString) VALUES ($placeholders)";
            $insertStmt = $sqlite_pdo->prepare($insertSql);

            $count = 0;
            foreach ($rows as $row) {
                $insertStmt->execute(array_values($row));
                $count++;
            }
            echo "<span style='color: green;'>Sukses ($count baris)</span><br>";
        } else {
            echo "<span style='color: orange;'>Kosong (0 baris)</span><br>";
        }
    }

    // Aktifkan kembali Foreign Key Check
    $sqlite_pdo->exec("PRAGMA foreign_keys = ON;");

    echo "<br><strong>🎉 Migrasi Selesai!</strong> Semua data dari MySQL sudah dipindahkan ke SQLite.<br>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 20px; padding: 10px 20px; background: #1e3a8a; color: white; text-decoration: none; border-radius: 5px;'>Kembali ke Dashboard</a>";

} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; border: 1px solid red;'>";
    echo "<strong>❌ Gagal Migrasi:</strong> " . $e->getMessage();
    echo "</div>";
}
