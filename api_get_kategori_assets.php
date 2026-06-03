<?php
require 'config.php';
header('Content-Type: application/json');

if(!isset($_SESSION['login'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// ✅ GET ALL KATEGORI (untuk dropdown)
if($action === 'get_all') {
    $result = mysqli_query($conn, "SELECT k.*, 
        (SELECT COUNT(*) FROM inventaris i WHERE i.kategori_id = k.nama_kategori) as total_aset,
        (SELECT COALESCE(SUM(total), 0) FROM inventaris i WHERE i.kategori_id = k.nama_kategori) as total_nilai
        FROM kategori_aset k 
        ORDER BY k.nama_kategori ASC");
    
    $kategori = [];
    while($row = mysqli_fetch_assoc($result)) {
        $row['icon'] = getCategoryIcon($row['nama_kategori']);
        $kategori[] = $row;
    }
    
    echo json_encode(['success' => true, 'data' => $kategori]);
    exit;
}

// ✅ QUICK ADD KATEGORI (dari form tambah aset)
if($action === 'quick_add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode = mysqli_real_escape_string($conn, strtoupper(trim($_POST['kode_kategori'])));
    $nama = mysqli_real_escape_string($conn, trim($_POST['nama_kategori']));
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan']));
    
    if(!$kode || !$nama) {
        echo json_encode(['success' => false, 'message' => 'Kode dan nama wajib diisi']);
        exit;
    }
    
    // Validasi duplicate
    $check = mysqli_query($conn, "SELECT id FROM kategori_aset WHERE kode_kategori = '$kode' OR nama_kategori = '$nama'");
    if(mysqli_num_rows($check) > 0) {
        echo json_encode(['success' => false, 'message' => 'Kode atau nama kategori sudah ada']);
        exit;
    }
    
    $query = "INSERT INTO kategori_aset (kode_kategori, nama_kategori, keterangan) VALUES ('$kode', '$nama', '$keterangan')";
    if(mysqli_query($conn, $query)) {
        $new_id = mysqli_insert_id($conn);
        echo json_encode([
            'success' => true, 
            'message' => 'Kategori berhasil ditambahkan',
            'data' => [
                'id' => $new_id,
                'kode_kategori' => $kode,
                'nama_kategori' => $nama,
                'keterangan' => $keterangan,
                'icon' => getCategoryIcon($nama),
                'total_aset' => 0,
                'total_nilai' => 0
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan kategori']);
    }
    exit;
}

// Helper function
function getCategoryIcon($nama) {
    $icons = [
        'Buku & Bahan Ajar' => 'fa-book',
        'Alat Tulis Kantor (ATK)' => 'fa-pen-fancy',
        'Perlengkapan Komputer & Printer' => 'fa-laptop',
        'Perlengkapan Kebersihan' => 'fa-broom',
        'Perlengkapan Kesehatan' => 'fa-heart-pulse',
        'Peralatan Olahraga' => 'fa-basketball',
        'Peralatan dan Sarana Pendukung Sekolah' => 'fa-school',
    ];
    return $icons[$nama] ?? 'fa-tag';
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);