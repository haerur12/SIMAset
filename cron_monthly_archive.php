<?php
/**
 * Cron Job: Auto-archive peminjaman bulan sebelumnya
 * Jalankan setiap tanggal 1 via cron:
 * 0 0 1 * * /usr/bin/php /path/to/cron_monthly_archive.php
 */

require 'config.php';

// Bulan & tahun sebelumnya
$bulan_lalu = date('n', strtotime('-1 month'));
$tahun_lalu = date('Y', strtotime('-1 month'));

echo "=== Auto Archive Peminjaman ===\n";
echo "Periode: " . date('F Y', mktime(0,0,0,$bulan_lalu,1,$tahun_lalu)) . "\n\n";

// Cek apakah sudah ada arsip
$existing = mysqli_query($conn, "SELECT id FROM peminjaman_arsip_bulanan WHERE bulan = $bulan_lalu AND tahun = $tahun_lalu");

if(mysqli_num_rows($existing) > 0) {
    echo "Arsip untuk periode ini sudah ada. Skipping...\n";
    exit;
}

// Hitung statistik
$stats = mysqli_query($conn, "SELECT 
    COUNT(*) as total_peminjaman,
    SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as total_dikembalikan,
    SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat,
    COALESCE(SUM(jumlah), 0) as total_unit,
    COALESCE(SUM(kondisi_rusak_ringan_kembali + kondisi_rusak_berat_kembali + kondisi_perbaikan_kembali), 0) as total_rusak
    FROM peminjaman_aset 
    WHERE MONTH(tanggal_pinjam) = $bulan_lalu AND YEAR(tanggal_pinjam) = $tahun_lalu")->fetch_assoc();

// Ambil detail
$detail = mysqli_query($conn, "SELECT id, peminjam, nip_peminjam, unit_kerja, no_hp, 
    tanggal_pinjam, tanggal_kembali_rencana, tanggal_kembali_aktual, status, jumlah, keperluan,
    kondisi_sebelum, kondisi_baik_pinjam, kondisi_rusak_ringan_pinjam, kondisi_rusak_berat_pinjam, kondisi_perbaikan_pinjam,
    kondisi_baik_kembali, kondisi_rusak_ringan_kembali, kondisi_rusak_berat_kembali, kondisi_perbaikan_kembali,
    catatan_pengembalian, petugas_serah_terima
    FROM peminjaman_aset p
    LEFT JOIN inventaris i ON p.inventaris_id = i.id
    WHERE MONTH(tanggal_pinjam) = $bulan_lalu AND YEAR(tanggal_pinjam) = $tahun_lalu");

$detail_array = [];
while($d = mysqli_fetch_assoc($detail)) {
    $detail_array[] = $d;
}

$detail_json = mysqli_real_escape_string($conn, json_encode($detail_array));

// Insert arsip
$insert = mysqli_query($conn, "INSERT INTO peminjaman_arsip_bulanan 
    (bulan, tahun, total_peminjaman, total_dikembalikan, total_terlambat, 
     total_unit_dipinjam, total_rusak_kembali, detail_json) 
    VALUES ($bulan_lalu, $tahun_lalu, {$stats['total_peminjaman']}, {$stats['total_dikembalikan']}, 
            {$stats['total_terlambat']}, {$stats['total_unit']}, {$stats['total_rusak']}, 
            '$detail_json')");

if($insert) {
    echo "✓ Arsip berhasil dibuat!\n";
    echo "  - Total Peminjaman: {$stats['total_peminjaman']}\n";
    echo "  - Total Unit: {$stats['total_unit']}\n";
    echo "  - Dikembalikan: {$stats['total_dikembalikan']}\n";
    echo "  - Terlambat: {$stats['total_terlambat']}\n";
    echo "  - Unit Rusak: {$stats['total_rusak']}\n";
} else {
    echo "✗ Gagal membuat arsip: " . mysqli_error($conn) . "\n";
}

// ============================================
// ✅ AUTO-RESET: Tandai peminjaman bulan lalu sebagai diarsipkan
// ============================================
$update = mysqli_query($conn, "UPDATE peminjaman_aset 
    SET diarsipkan = 1, bulan_arsip = $bulan_lalu, tahun_arsip = $tahun_lalu
    WHERE MONTH(tanggal_pinjam) = $bulan_lalu 
    AND YEAR(tanggal_pinjam) = $tahun_lalu 
    AND diarsipkan = 0");

if($update) {
    echo "✓ " . mysqli_affected_rows($conn) . " peminjaman ditandai sebagai diarsipkan\n";
}

echo "\n=== Selesai ===\n";
?>