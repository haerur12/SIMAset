<?php
/**
 * API: Cek status notifikasi arsip bulanan
 * Dipanggil via AJAX dari dashboard
 */
require 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['login'])) {
    echo json_encode(['show' => false, 'error' => 'Unauthorized']);
    exit;
}

// Cek waktu arsip
$result = cek_waktu_arsip();

// Jika perlu tampilkan notifikasi, ambil info tambahan
if($result['show']) {
    $bulan = $result['bulan'];
    $tahun = $result['tahun'];
    
    // Hitung jumlah data peminjaman bulan target
    $count_result = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset 
        WHERE MONTH(tanggal_pinjam) = $bulan AND YEAR(tanggal_pinjam) = $tahun");
    $jumlah_data = $count_result ? mysqli_fetch_assoc($count_result)['total'] : 0;
    
    // Hitung total unit
    $unit_result = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM peminjaman_aset 
        WHERE MONTH(tanggal_pinjam) = $bulan AND YEAR(tanggal_pinjam) = $tahun");
    $total_unit = $unit_result ? mysqli_fetch_assoc($unit_result)['total'] : 0;
    
    $result['jumlah_data'] = $jumlah_data;
    $result['total_unit'] = $total_unit;
    $result['nama_bulan'] = nama_bulan_indo($bulan);
}

echo json_encode($result);