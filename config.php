<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "123";
$db   = "db_inventaris_sekolah";

$conn = mysqli_connect("localhost", "root", "", "db_inventaris_sekolah");

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

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