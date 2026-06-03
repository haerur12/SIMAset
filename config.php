<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error ke user

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "db_inventaris_sekolah";

$conn = mysqli_connect("127.0.0.1", "root", "", "db_inventaris_sekolah", 3306);

if (!$conn) {
    error_log("Database Connection Error: " . mysqli_connect_error(), 3, "logs/error.log");
    die("Koneksi database gagal. Hubungi administrator.");
}

// Set charset UTF-8
mysqli_set_charset($conn, "utf8mb4");

// Fungsi untuk format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if(empty($tanggal)) return "-";
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}
?>