<?php
// api_detail_aset.php - Endpoint AJAX untuk fetch detail aset
require 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID tidak valid']);
    exit;
}

// Query detail aset lengkap
$query = "SELECT 
    i.*,
    r.nama_ruangan,
    r.kode_ruangan,
    r.gedung,
    r.lantai,
    ks.kondisi AS kondisi_terkini,
    ks.tanggal_cek AS tgl_cek_kondisi,
    ks.keterangan AS ket_kondisi,
    ks.petugas AS petugas_cek
FROM inventaris i
LEFT JOIN ruangan r ON i.ruangan_id = r.id
LEFT JOIN (
    SELECT k1.inventaris_id, k1.kondisi, k1.tanggal_cek, k1.keterangan, k1.petugas
    FROM kondisi_aset k1
    INNER JOIN (
        SELECT inventaris_id, MAX(created_at) AS max_created_at
        FROM kondisi_aset
        GROUP BY inventaris_id
    ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
) ks ON ks.inventaris_id = i.id
WHERE i.id = $id
LIMIT 1";

$result = mysqli_query($conn, $query);

if(!$result || mysqli_num_rows($result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan']);
    exit;
}

$data = mysqli_fetch_assoc($result);

// Hitung total riwayat kondisi
$riwayat_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM kondisi_aset WHERE inventaris_id = $id")->fetch_assoc()['total'];

// Hitung total tracking
$tracking_count = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset WHERE inventaris_id = $id")->fetch_assoc()['total'];

// Tambahkan data tambahan
$data['riwayat_count'] = (int)$riwayat_count;
$data['tracking_count'] = (int)$tracking_count;

echo json_encode([
    'success' => true,
    'data' => $data
]);