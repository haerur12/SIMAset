<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ UPDATE JUMLAH KONDISI ASET (INSERT or UPDATE)
if(isset($_POST['update_jumlah_kondisi'])) {
    // ✅ PROTEKSI: Hanya admin yang bisa update kondisi
    requireAccess('update', 'kondisi_aset.php');
    $inventaris_id = intval($_POST['inventaris_id']);
    $jumlah_baik = intval($_POST['jumlah_baik'] ?? 0);
    $jumlah_rusak_ringan = intval($_POST['jumlah_rusak_ringan'] ?? 0);
    $jumlah_rusak_berat = intval($_POST['jumlah_rusak_berat'] ?? 0);
    $jumlah_perbaikan = intval($_POST['jumlah_perbaikan'] ?? 0);
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan'] ?? ''));
    $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
    $tanggal_cek = date('Y-m-d');
    
    // Validasi
    $total = $jumlah_baik + $jumlah_rusak_ringan + $jumlah_rusak_berat + $jumlah_perbaikan;
    if($total == 0) {
        header("Location: kondisi_aset.php?detail_id=$inventaris_id&action=empty");
        exit;
    }
    
    // Tentukan kondisi utama
    $kondisi_utama = 'Baik';
    $max = $jumlah_baik;
    
    if($jumlah_rusak_ringan > $max) { $kondisi_utama = 'Rusak Ringan'; $max = $jumlah_rusak_ringan; }
    if($jumlah_rusak_berat > $max) { $kondisi_utama = 'Rusak Berat'; $max = $jumlah_rusak_berat; }
    if($jumlah_perbaikan > $max) { $kondisi_utama = 'Dalam Perbaikan'; $max = $jumlah_perbaikan; }
    
    // Buat detail JSON
    $detail_json = json_encode([
        'baik' => $jumlah_baik,
        'rusak_ringan' => $jumlah_rusak_ringan,
        'rusak_berat' => $jumlah_rusak_berat,
        'dalam_perbaikan' => $jumlah_perbaikan,
        'total' => $total
    ]);
    
    $keterangan_lengkap = "[DETAIL] Baik:$jumlah_baik | Rusak Ringan:$jumlah_rusak_ringan | Rusak Berat:$jumlah_rusak_berat | Perbaikan:$jumlah_perbaikan | Total:$total. " . $keterangan;
    
    // ✅ CEK apakah sudah ada record untuk aset ini
    $check_existing = mysqli_query($conn, "SELECT id FROM kondisi_aset WHERE inventaris_id = $inventaris_id ORDER BY created_at DESC LIMIT 1");
    
    // Cek kolom detail_kondisi
    $check_col = mysqli_query($conn, "SHOW COLUMNS FROM kondisi_aset LIKE 'detail_kondisi'");
    $has_detail_col = mysqli_num_rows($check_col) > 0;
    
    // ✅ UPDATE or INSERT
    if(mysqli_num_rows($check_existing) > 0) {
        $existing = mysqli_fetch_assoc($check_existing);
        $existing_id = $existing['id'];
        
        if($has_detail_col) {
            $stmt = mysqli_prepare($conn, "UPDATE kondisi_aset SET 
                kondisi = ?, tanggal_cek = ?, keterangan = ?, petugas = ?, detail_kondisi = ? 
                WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "sssssi", $kondisi_utama, $tanggal_cek, $keterangan_lengkap, $petugas, $detail_json, $existing_id);
        } else {
            $stmt = mysqli_prepare($conn, "UPDATE kondisi_aset SET 
                kondisi = ?, tanggal_cek = ?, keterangan = ?, petugas = ? 
                WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssssi", $kondisi_utama, $tanggal_cek, $keterangan_lengkap, $petugas, $existing_id);
        }
        
        $action_type = 'updated';
    } else {
        if($has_detail_col) {
            $stmt = mysqli_prepare($conn, "INSERT INTO kondisi_aset (inventaris_id, kondisi, tanggal_cek, keterangan, petugas, detail_kondisi) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "isssss", $inventaris_id, $kondisi_utama, $tanggal_cek, $keterangan_lengkap, $petugas, $detail_json);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO kondisi_aset (inventaris_id, kondisi, tanggal_cek, keterangan, petugas) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issss", $inventaris_id, $kondisi_utama, $tanggal_cek, $keterangan_lengkap, $petugas);
        }
        
        $action_type = 'inserted';
    }
    
    if(mysqli_stmt_execute($stmt)) {
        $check_inv_col = mysqli_query($conn, "SHOW COLUMNS FROM inventaris LIKE 'kondisi_aset'");
        if(mysqli_num_rows($check_inv_col) > 0) {
            mysqli_query($conn, "UPDATE inventaris SET kondisi_aset = '$kondisi_utama' WHERE id = $inventaris_id");
        }
        
        header("Location: kondisi_aset.php?detail_id=$inventaris_id&action=$action_type");
        exit;
    } else {
        header("Location: kondisi_aset.php?detail_id=$inventaris_id&action=error&msg=" . urlencode(mysqli_stmt_error($stmt)));
        exit;
    }
}

// ✅ FILTER BERDASARKAN KATEGORI
$filter_kategori = isset($_GET['kategori']) ? mysqli_real_escape_string($conn, $_GET['kategori']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where_clauses = [];
if($filter_kategori && $filter_kategori !== 'all') {
    $where_clauses[] = "i.kategori_id = '$filter_kategori'";
}
if($search) {
    $where_clauses[] = "(i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%')";
}
$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

// Query utama
$query = "SELECT i.*, r.nama_ruangan, r.kode_ruangan, i.kategori_id, 
                 ks.kondisi AS kondisi_aset, ks.tanggal_cek AS last_tanggal_cek, 
                 ks.keterangan AS last_keterangan, ks.petugas AS last_petugas
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
          $where
          ORDER BY 
            CASE ks.kondisi 
                WHEN 'Rusak Berat' THEN 1 
                WHEN 'Rusak Ringan' THEN 2 
                WHEN 'Dalam Perbaikan' THEN 3 
                WHEN 'Baik' THEN 4 
                ELSE 5 
            END,
            i.created_at DESC";
$result = mysqli_query($conn, $query);

// ============================================================
// ✅ STATISTIK KONDISI - DIPERBAIKI (HANYA RECORD TERBARU)
// ============================================================
// Query ini hanya mengambil record TERBARU per inventaris_id
// sehingga tidak ada duplikasi perhitungan

$check_detail_col = mysqli_query($conn, "SHOW COLUMNS FROM kondisi_aset LIKE 'detail_kondisi'");
$has_detail_col = mysqli_num_rows($check_detail_col) > 0;

if($has_detail_col) {
    // ✅ QUERY YANG BENAR: Hanya ambil record terbaru per inventaris_id
    $stats_query = "SELECT 
        COALESCE(SUM(JSON_EXTRACT(detail_kondisi, '$.baik')), 0) as baik,
        COALESCE(SUM(JSON_EXTRACT(detail_kondisi, '$.rusak_ringan')), 0) as rusak_ringan,
        COALESCE(SUM(JSON_EXTRACT(detail_kondisi, '$.rusak_berat')), 0) as rusak_berat,
        COALESCE(SUM(JSON_EXTRACT(detail_kondisi, '$.dalam_perbaikan')), 0) as perbaikan,
        COALESCE(SUM(JSON_EXTRACT(detail_kondisi, '$.total')), 0) as total_unit,
        COUNT(*) as total_aset_dicek
    FROM (
        SELECT k1.detail_kondisi, k1.inventaris_id
        FROM kondisi_aset k1
        INNER JOIN (
            SELECT inventaris_id, MAX(created_at) AS max_created_at
            FROM kondisi_aset
            WHERE detail_kondisi IS NOT NULL AND detail_kondisi != '' AND detail_kondisi != 'null'
            GROUP BY inventaris_id
        ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    ) AS latest";
    
    $stats = mysqli_query($conn, $stats_query)->fetch_assoc();
    
    $stat_baik = intval($stats['baik'] ?? 0);
    $stat_rusak_ringan = intval($stats['rusak_ringan'] ?? 0);
    $stat_rusak_berat = intval($stats['rusak_berat'] ?? 0);
    $stat_perbaikan = intval($stats['perbaikan'] ?? 0);
    $total_unit_checked = intval($stats['total_unit'] ?? 0);
    $total_aset_dicek = intval($stats['total_aset_dicek'] ?? 0);
    
} else {
    // Fallback untuk MySQL lama atau tanpa detail_kondisi
    $stats_query = "SELECT 
        COUNT(DISTINCT inventaris_id) as total_aset_dicek,
        SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as baik,
        SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) as rusak_ringan,
        SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) as rusak_berat,
        SUM(CASE WHEN kondisi = 'Dalam Perbaikan' THEN 1 ELSE 0 END) as perbaikan
    FROM (
        SELECT k1.inventaris_id, k1.kondisi
        FROM kondisi_aset k1
        INNER JOIN (
            SELECT inventaris_id, MAX(created_at) AS max_created_at
            FROM kondisi_aset
            GROUP BY inventaris_id
        ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    ) AS latest";
    
    $stats = mysqli_query($conn, $stats_query)->fetch_assoc();
    $stat_baik = intval($stats['baik'] ?? 0);
    $stat_rusak_ringan = intval($stats['rusak_ringan'] ?? 0);
    $stat_rusak_berat = intval($stats['rusak_berat'] ?? 0);
    $stat_perbaikan = intval($stats['perbaikan'] ?? 0);
    $total_aset_dicek = intval($stats['total_aset_dicek'] ?? 0);
    $total_unit_checked = $stat_baik + $stat_rusak_ringan + $stat_rusak_berat + $stat_perbaikan;
}

// ✅ TOTAL SEMUA ASET DI INVENTARIS
$total_aset_inventaris = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];
$total_belum_dicek = max(0, $total_aset_inventaris - $total_aset_dicek);

// ✅ HEALTH SCORE: Persentase unit yang dalam kondisi BAIK
// Rumus: (unit_baik / total_unit_dicek) × 100
// Contoh: 180 unit baik dari 200 unit dicek = 90%
$health_percent = $total_unit_checked > 0 ? round(($stat_baik / $total_unit_checked) * 100) : 0;

// Batasi health_percent maksimal 100%
$health_percent = min(100, max(0, $health_percent));

// 7 Kategori Fixed
$daftar_kategori = [
    'Buku & Bahan Ajar',
    'Alat Tulis Kantor (ATK)',
    'Perlengkapan Komputer & Printer',
    'Perlengkapan Kebersihan',
    'Perlengkapan Kesehatan',
    'Peralatan Olahraga',
    'Peralatan dan Sarana Pendukung Sekolah'
];

// ✅ DETAIL ASET (untuk modal detail)
$detail_aset = null;
$riwayat_kondisi = null;
if(isset($_GET['detail_id'])) {
    $detail_id = intval($_GET['detail_id']);
    $detail_aset = mysqli_query($conn, "SELECT i.*, r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id WHERE i.id = $detail_id")->fetch_assoc();
    
    if($detail_aset) {
        $riwayat_kondisi = mysqli_query($conn, "SELECT * FROM kondisi_aset WHERE inventaris_id = $detail_id ORDER BY created_at DESC");
    }
}
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kondisi Aset - Inventaris SDN Curug 01</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: { DEFAULT: '#1a365d', dark: '#0f2744', light: '#2c5282' }
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif']
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'slide-in-left': 'slideInLeft 0.3s ease-out'
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                        slideInLeft: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(0)' } }
                    }
                }
            }
        }
    </script>
    
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #1a365d; border-radius: 4px; }
        .dark ::-webkit-scrollbar-track { background: #2d3748; }
        
        * { transition: background-color 0.3s ease, color 0.2s ease, border-color 0.3s ease; }
        
        .stagger-item { animation: slideUp 0.5s ease-out backwards; }
        .stagger-item:nth-child(1) { animation-delay: 0.1s; }
        .stagger-item:nth-child(2) { animation-delay: 0.2s; }
        .stagger-item:nth-child(3) { animation-delay: 0.3s; }
        .stagger-item:nth-child(4) { animation-delay: 0.4s; }
        
        .badge-critical { animation: pulse 2s infinite; }
        
        .filter-pill.active {
            background: linear-gradient(135deg, #1a365d, #2c5282);
            color: white;
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
        }
        
        .jumlah-input {
            transition: all 0.2s;
        }
        .jumlah-input:focus {
            transform: scale(1.02);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="kondisiApp()">
    
    <div x-show="sidebarOpen" 
         x-transition:enter="transition-opacity ease-linear duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-300"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-40 lg:hidden"
         style="display: none;"></div>


    <?php include 'sidebar.php'; ?>
    
    <main class="flex-1 flex flex-col min-w-0 overflow-x-hidden">
        
        <header class="bg-white dark:bg-gray-800 shadow-sm px-4 lg:px-8 py-4 flex items-center justify-between sticky top-0 z-30">
            <div class="flex items-center gap-4">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div>
                    <nav class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-1">
                        <a href="dashboard.php" class="hover:text-primary">Dashboard</a>
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="text-primary font-semibold">Kondisi Aset</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-heart-pulse"></i>
                        <span>Monitoring Kondisi Aset</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <button @click="showHealthReport()" 
                        class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-chart-pie"></i>
                    <span class="text-sm font-medium">Laporan</span>
                </button>
            </div>
        </header>
        
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Health Score Card -->
            <div class="bg-gradient-to-r from-primary via-primary-light to-primary rounded-2xl shadow-xl p-6 lg:p-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
                
                <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                                <i class="fas fa-heart-pulse text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-bold">Health Score Aset</h3>
                                <p class="text-sm text-white/80">Tingkat kesehatan aset sekolah berdasarkan unit yang dicek</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-end gap-2 mb-2">
                                <span class="text-5xl font-bold"><?= $health_percent ?>%</span>
                                <span class="text-lg text-white/80 mb-1">
                                    <?php if($health_percent >= 80): ?>
                                        <i class="fas fa-smile mr-1"></i> Sangat Baik
                                    <?php elseif($health_percent >= 60): ?>
                                        <i class="fas fa-meh mr-1"></i> Cukup Baik
                                    <?php elseif($health_percent >= 40): ?>
                                        <i class="fas fa-frown mr-1"></i> Perlu Perhatian
                                    <?php else: ?>
                                        <i class="fas fa-exclamation-triangle mr-1"></i> Kritis
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="w-full bg-white/20 rounded-full h-3 overflow-hidden">
                                <div class="h-full rounded-full transition-all duration-1000 ease-out
                                    <?= $health_percent >= 80 ? 'bg-emerald-400' : ($health_percent >= 60 ? 'bg-yellow-400' : ($health_percent >= 40 ? 'bg-orange-400' : 'bg-red-400')) ?>"
                                    style="width: <?= $health_percent ?>%"></div>
                            </div>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="bg-white/10 backdrop-blur rounded-lg p-2 text-center">
                                    <p class="text-xs text-white/70">Total Aset</p>
                                    <p class="text-lg font-bold"><?= $total_aset_inventaris ?></p>
                                </div>
                                <div class="bg-white/10 backdrop-blur rounded-lg p-2 text-center">
                                    <p class="text-xs text-white/70">Sudah Dicek</p>
                                    <p class="text-lg font-bold"><?= $total_aset_dicek ?></p>
                                </div>
                                <div class="bg-white/10 backdrop-blur rounded-lg p-2 text-center">
                                    <p class="text-xs text-white/70">Belum Dicek</p>
                                    <p class="text-lg font-bold text-amber-300"><?= $total_belum_dicek ?></p>
                                </div>
                            </div>
                            <!-- ✅ Info Tambahan: Total Unit -->
                            <div class="mt-2 text-xs text-white/70 text-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Total unit dicek: <strong><?= number_format($total_unit_checked) ?></strong> unit | 
                                Unit baik: <strong><?= number_format($stat_baik) ?></strong> unit
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/10 backdrop-blur rounded-xl p-4 text-center">
                            <i class="fas fa-check-circle text-2xl mb-2 text-emerald-300"></i>
                            <p class="text-2xl font-bold"><?= number_format($stat_baik) ?></p>
                            <p class="text-xs text-white/80">Unit Baik</p>
                        </div>
                        <div class="bg-white/10 backdrop-blur rounded-xl p-4 text-center">
                            <i class="fas fa-exclamation-triangle text-2xl mb-2 text-amber-300"></i>
                            <p class="text-2xl font-bold"><?= number_format($stat_rusak_ringan + $stat_rusak_berat) ?></p>
                            <p class="text-xs text-white/80">Unit Rusak</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <?php 
                $stats_cards = [
                    ['Baik', $stat_baik, 'fa-check-circle', 'from-emerald-500 to-emerald-600'],
                    ['Rusak Ringan', $stat_rusak_ringan, 'fa-exclamation-triangle', 'from-amber-500 to-amber-600'],
                    ['Rusak Berat', $stat_rusak_berat, 'fa-times-circle', 'from-red-500 to-red-600'],
                    ['Dalam Perbaikan', $stat_perbaikan, 'fa-wrench', 'from-blue-500 to-blue-600'],
                ];
                foreach($stats_cards as $idx => $stat): 
                    // ✅ Persentase berdasarkan total unit yang dicek (bukan total aset)
                    $percentage = $total_unit_checked > 0 ? round(($stat[1] / $total_unit_checked) * 100) : 0;
                ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-5 lg:p-6">
                        <div class="flex items-start justify-between mb-3">
                            <div class="p-3 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-lg"></i>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                                <?= $percentage ?>%
                            </span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-white"><?= number_format($stat[1]) ?></h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            <i class="fas fa-cube mr-1"></i>
                            Unit termonitor
                        </p>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filter & Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden" x-data="{ showFilter: false }">
                
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-primary/10 rounded-lg">
                                    <i class="fas fa-list-check text-primary text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Daftar Kondisi Aset</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Menampilkan <span class="font-semibold text-primary"><?= mysqli_num_rows($result) ?></span> aset
                                        <?php if($filter_kategori && $filter_kategori !== 'all'): ?>
                                            • Kategori: <span class="font-semibold text-primary"><?= htmlspecialchars($filter_kategori) ?></span>
                                        <?php endif; ?>
                                        <?php if($search): ?>
                                            • Search: <span class="font-semibold text-primary">"<?= htmlspecialchars($search) ?>"</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <form method="GET" class="flex gap-2 w-full lg:w-auto">
                                <?php if($filter_kategori): ?>
                                <input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kategori) ?>">
                                <?php endif; ?>
                                <div class="relative flex-1 lg:w-80">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" 
                                           name="search" 
                                           placeholder="Cari nama/spesifikasi aset..." 
                                           value="<?= htmlspecialchars($search) ?>"
                                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                </div>
                                <button type="submit" 
                                        class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm font-medium">
                                    <i class="fas fa-search"></i>
                                    <span class="hidden sm:inline">Cari</span>
                                </button>
                            </form>
                        </div>
                        
                        <div class="flex items-center justify-between gap-2">
                            <button @click="showFilter = !showFilter" 
                                    type="button"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all text-sm font-medium">
                                <i class="fas fa-filter" :class="showFilter ? 'text-primary' : ''"></i>
                                <span>Filter Kategori</span>
                                <?php if($filter_kategori && $filter_kategori !== 'all'): ?>
                                <span class="ml-1 px-2 py-0.5 bg-primary text-white text-[10px] rounded-full">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down ml-1 transition-transform" :class="showFilter ? 'rotate-180' : ''"></i>
                            </button>
                            
                            <?php if($filter_kategori || $search): ?>
                            <a href="kondisi_aset.php" 
                               class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 rounded-lg transition-all text-sm font-medium">
                                <i class="fas fa-times"></i>
                                <span>Reset</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div x-show="showFilter" 
                             x-transition
                             class="flex flex-wrap gap-2 pt-3 border-t border-gray-100 dark:border-gray-700"
                             style="display: none;">
                            
                            <a href="kondisi_aset.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= (!$filter_kategori || $filter_kategori === 'all') ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-layer-group"></i>
                                <span>Semua</span>
                            </a>
                            
                            <?php 
                            $kategori_icons = [
                                'Buku & Bahan Ajar' => 'fa-book',
                                'Alat Tulis Kantor (ATK)' => 'fa-pen-fancy',
                                'Perlengkapan Komputer & Printer' => 'fa-laptop',
                                'Perlengkapan Kebersihan' => 'fa-broom',
                                'Perlengkapan Kesehatan' => 'fa-heart-pulse',
                                'Peralatan Olahraga' => 'fa-basketball',
                                'Peralatan dan Sarana Pendukung Sekolah' => 'fa-school',
                            ];
                            foreach($daftar_kategori as $kat): 
                            ?>
                            <a href="?kategori=<?= urlencode($kat) ?><?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-3 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_kategori === $kat ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas <?= $kategori_icons[$kat] ?? 'fa-tag' ?>"></i>
                                <span><?= $kat ?></span>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">No</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Nama Barang</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Kategori</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Lokasi</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Kondisi</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Terakhir Dicek</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if(mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-clipboard-check text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data ditemukan</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else:
                                $no = 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $currentCondition = $row['kondisi_aset'] ?? 'Belum Dicek';
                                    
                                    $badgeClasses = [
                                        'Baik' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800',
                                        'Rusak Ringan' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800',
                                        'Rusak Berat' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300 dark:border-red-800 badge-critical',
                                        'Dalam Perbaikan' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300 dark:border-blue-800',
                                        'Tidak Layak Pakai' => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-700 dark:text-gray-300 dark:border-gray-600',
                                        'Belum Dicek' => 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-700 dark:text-gray-400 dark:border-gray-600'
                                    ];
                                    $badgeClass = $badgeClasses[$currentCondition] ?? $badgeClasses['Belum Dicek'];
                                    
                                    $iconClasses = [
                                        'Baik' => 'fa-check-circle text-emerald-500',
                                        'Rusak Ringan' => 'fa-exclamation-triangle text-amber-500',
                                        'Rusak Berat' => 'fa-times-circle text-red-500',
                                        'Dalam Perbaikan' => 'fa-wrench text-blue-500',
                                        'Tidak Layak Pakai' => 'fa-ban text-gray-500',
                                        'Belum Dicek' => 'fa-question-circle text-gray-400'
                                    ];
                                    $iconClass = $iconClasses[$currentCondition] ?? $iconClasses['Belum Dicek'];
                                    
                                    $tanggal_cek = $row['last_tanggal_cek'] ?? null;
                                    $days_ago = $tanggal_cek ? floor((time() - strtotime($tanggal_cek)) / 86400) : 999;
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">
                                <td class="px-4 py-4 text-sm font-medium text-gray-600 dark:text-gray-300"><?= $no++ ?></td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary/10 to-primary/20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-box text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="text-sm font-semibold text-gray-800 dark:text-white line-clamp-1">
                                                <?= htmlspecialchars($row['nama_barang_108']) ?>
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 line-clamp-1 max-w-xs">
                                                <?= htmlspecialchars(substr($row['spesifikasi_nama_barang'] ?? '-', 0, 40)) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                        <i class="fas fa-tag text-[10px] mr-1.5"></i>
                                        <?= htmlspecialchars($row['kategori_id'] ?: 'Umum') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= htmlspecialchars($row['nama_ruangan'] ?? '-') ?></div>
                                        <?php if($row['kode_ruangan']): ?>
                                            <div class="text-xs text-gray-500 dark:text-gray-400 font-mono"><?= htmlspecialchars($row['kode_ruangan']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border <?= $badgeClass ?>">
                                        <i class="fas <?= $iconClass ?> text-xs"></i>
                                        <?= $currentCondition ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">
                                        <?php if($tanggal_cek): ?>
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                            <span><?= formatTanggal($tanggal_cek) ?></span>
                                        </div>
                                        <div class="text-xs mt-0.5 
                                            <?= $days_ago > 30 ? 'text-red-500 font-semibold' : ($days_ago > 7 ? 'text-amber-500' : 'text-emerald-500') ?>">
                                            <?php if($days_ago == 0): ?>
                                                <i class="fas fa-clock mr-1"></i> Hari ini
                                            <?php elseif($days_ago == 1): ?>
                                                <i class="fas fa-clock mr-1"></i> Kemarin
                                            <?php else: ?>
                                                <i class="fas fa-clock mr-1"></i> <?= $days_ago ?> hari lalu
                                            <?php endif; ?>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-gray-400 italic text-xs">
                                            <i class="fas fa-minus-circle mr-1"></i> Belum pernah dicek
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button onclick="openDetailModal(<?= $row['id'] ?>)"
                                                class="p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                                title="Detail & Update Kondisi">
                                            <i class="fas fa-info-circle text-sm"></i>
                                        </button>
                                        <a href="riwayat_kondisi.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-purple-100 hover:bg-purple-500 text-purple-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Riwayat Kondisi">
                                            <i class="fas fa-history text-sm"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </main>
</div>

<!-- ✅ MODAL: DETAIL KONDISI ASET -->
<div id="detailModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-500 to-blue-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-info-circle text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Detail Kondisi Aset</h3>
                    <p class="text-xs text-white/80">Update jumlah kondisi per kategori</p>
                </div>
            </div>
            <button onclick="closeDetailModal()" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto p-5 space-y-5">
            <?php if($detail_aset): ?>
            <div class="bg-gradient-to-br from-primary/5 to-primary/10 dark:from-primary/20 dark:to-primary/10 rounded-xl p-4 border border-primary/20">
                <div class="flex items-start gap-4">
                    <div class="w-16 h-16 bg-primary/10 rounded-xl flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-box text-primary text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-lg font-bold text-gray-800 dark:text-white mb-1"><?= htmlspecialchars($detail_aset['nama_barang_108'] ?? '') ?></h4>
                        <p class="text-sm text-gray-600 dark:text-gray-300 mb-2"><?= htmlspecialchars($detail_aset['spesifikasi_nama_barang'] ?? '') ?></p>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span class="px-2 py-1 bg-primary/10 text-primary rounded-md">
                                <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($detail_aset['kategori_id'] ?? 'Umum') ?>
                            </span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded-md">
                                <i class="fas fa-door-open mr-1"></i><?= htmlspecialchars($detail_aset['nama_ruangan'] ?? '-') ?>
                            </span>
                            <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded-md font-bold">
                                <i class="fas fa-cube mr-1"></i>Total: <?= $detail_aset['jumlah'] ?> <?= $detail_aset['satuan'] ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if(canUpdate()): ?>
            <form method="POST" action="kondisi_aset.php?detail_id=<?= $detail_aset['id'] ?>" class="space-y-4" id="formKondisi">
                <input type="hidden" name="inventaris_id" value="<?= $detail_aset['id'] ?>">
                
                <div>
                    <label class="flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-white mb-3">
                        <i class="fas fa-clipboard-list text-primary"></i>
                        Input Jumlah Kondisi Aset
                        <span class="text-xs font-normal text-gray-500 ml-auto">Total tersedia: <?= $detail_aset['jumlah'] ?> <?= $detail_aset['satuan'] ?></span>
                    </label>
                    
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border-2 border-emerald-200 dark:border-emerald-800">
                            <label class="flex items-center gap-2 text-xs font-semibold text-emerald-700 dark:text-emerald-300 mb-2">
                                <i class="fas fa-check-circle"></i>
                                Kondisi Baik
                            </label>
                            <input type="number" name="jumlah_baik" min="0" max="<?= $detail_aset['jumlah'] ?>" value="0" 
                                   class="jumlah-input w-full px-3 py-2 bg-white dark:bg-gray-700 border border-emerald-300 dark:border-emerald-700 rounded-lg text-center font-bold text-emerald-700 dark:text-emerald-300 focus:outline-none"
                                   oninput="updateTotalJumlah()">
                        </div>
                        
                        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border-2 border-amber-200 dark:border-amber-800">
                            <label class="flex items-center gap-2 text-xs font-semibold text-amber-700 dark:text-amber-300 mb-2">
                                <i class="fas fa-exclamation-triangle"></i>
                                Rusak Ringan
                            </label>
                            <input type="number" name="jumlah_rusak_ringan" min="0" max="<?= $detail_aset['jumlah'] ?>" value="0" 
                                   class="jumlah-input w-full px-3 py-2 bg-white dark:bg-gray-700 border border-amber-300 dark:border-amber-700 rounded-lg text-center font-bold text-amber-700 dark:text-amber-300 focus:outline-none"
                                   oninput="updateTotalJumlah()">
                        </div>
                        
                        <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg border-2 border-red-200 dark:border-red-800">
                            <label class="flex items-center gap-2 text-xs font-semibold text-red-700 dark:text-red-300 mb-2">
                                <i class="fas fa-times-circle"></i>
                                Rusak Berat
                            </label>
                            <input type="number" name="jumlah_rusak_berat" min="0" max="<?= $detail_aset['jumlah'] ?>" value="0" 
                                   class="jumlah-input w-full px-3 py-2 bg-white dark:bg-gray-700 border border-red-300 dark:border-red-700 rounded-lg text-center font-bold text-red-700 dark:text-red-300 focus:outline-none"
                                   oninput="updateTotalJumlah()">
                        </div>
                        
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border-2 border-blue-200 dark:border-blue-800">
                            <label class="flex items-center gap-2 text-xs font-semibold text-blue-700 dark:text-blue-300 mb-2">
                                <i class="fas fa-wrench"></i>
                                Dalam Perbaikan
                            </label>
                            <input type="number" name="jumlah_perbaikan" min="0" max="<?= $detail_aset['jumlah'] ?>" value="0" 
                                   class="jumlah-input w-full px-3 py-2 bg-white dark:bg-gray-700 border border-blue-300 dark:border-blue-700 rounded-lg text-center font-bold text-blue-700 dark:text-blue-300 focus:outline-none"
                                   oninput="updateTotalJumlah()">
                        </div>
                    </div>
                    
                    <div id="totalInfo" class="mt-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-calculator text-primary"></i>
                            <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">Total Diinput:</span>
                            <strong id="totalJumlah" class="text-lg text-primary">0</strong>
                            <span class="text-sm text-gray-500">dari <?= $detail_aset['jumlah'] ?> <?= $detail_aset['satuan'] ?></span>
                        </div>
                        <span id="totalStatus" class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-200 text-gray-600">
                            Belum lengkap
                        </span>
                    </div>
                </div>
                
                <div>
                    <label class="flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-white mb-2">
                        <i class="fas fa-note-sticky text-primary"></i>
                        Keterangan Pemeriksaan
                    </label>
                    <textarea name="keterangan" rows="3" 
                              placeholder="Catatan tambahan tentang kondisi aset..."
                              class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm resize-none"></textarea>
                </div>
                
                <div class="flex items-center justify-end gap-2 pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeDetailModal()" 
                            class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all">
                        Batal
                    </button>
                    <button type="submit" name="update_jumlah_kondisi" 
                            class="px-5 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-lg shadow-md text-sm font-semibold transition-all flex items-center gap-2">
                        <i class="fas fa-save"></i>
                        Simpan Kondisi
                    </button>
                </div>
            </form>
            <?php else: ?>
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-4 flex items-center gap-3">
                <div class="p-2 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                    <i class="fas fa-eye text-blue-600 dark:text-blue-300 text-xl"></i>
                </div>
                <div class="flex-1">
                    <p class="font-semibold text-blue-900 dark:text-blue-300">Mode View Only</p>
                    <p class="text-sm text-blue-700 dark:text-blue-400">Anda hanya dapat melihat data kondisi. Untuk update, silakan hubungi admin.</p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <h4 class="text-sm font-bold text-gray-800 dark:text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-history text-primary"></i>
                    Riwayat Pemeriksaan
                </h4>
                <div class="space-y-2 max-h-64 overflow-y-auto">
                    <?php if($riwayat_kondisi && mysqli_num_rows($riwayat_kondisi) > 0): ?>
                        <?php while($row = mysqli_fetch_assoc($riwayat_kondisi)): 
                            $row_color = match($row['kondisi']) {
                                'Baik' => 'border-emerald-500 bg-emerald-50 dark:bg-emerald-900/20',
                                'Rusak Ringan' => 'border-amber-500 bg-amber-50 dark:bg-amber-900/20',
                                'Rusak Berat' => 'border-red-500 bg-red-50 dark:bg-red-900/20',
                                'Dalam Perbaikan' => 'border-blue-500 bg-blue-50 dark:bg-blue-900/20',
                                default => 'border-gray-500 bg-gray-50 dark:bg-gray-700/50'
                            };
                        ?>
                        <div class="p-3 rounded-lg border-l-4 <?= $row_color ?>">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-gray-800 dark:text-white">
                                    <?= $row['kondisi'] ?>
                                </span>
                                <span class="text-[10px] text-gray-500">
                                    <?= date('d M Y, H:i', strtotime($row['created_at'])) ?>
                                </span>
                            </div>
                            <?php if($row['keterangan']): ?>
                            <p class="text-xs text-gray-600 dark:text-gray-300"><?= nl2br(htmlspecialchars($row['keterangan'])) ?></p>
                            <?php endif; ?>
                            <p class="text-[10px] text-gray-400 mt-1">
                                <i class="fas fa-user mr-1"></i><?= htmlspecialchars($row['petugas']) ?>
                            </p>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-2"></i>
                            <p class="text-sm">Belum ada riwayat pemeriksaan</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-12">
                <i class="fas fa-spinner fa-spin text-3xl text-primary mb-3"></i>
                <p class="text-gray-500">Memuat data...</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-[1100] space-y-2"></div>

<script>
function openDetailModal(id) {
    const url = new URL(window.location);
    url.searchParams.set('detail_id', id);
    url.searchParams.delete('action');
    url.searchParams.delete('msg');
    window.location.href = url.toString();
}

function closeDetailModal() {
    const url = new URL(window.location);
    url.searchParams.delete('detail_id');
    url.searchParams.delete('action');
    url.searchParams.delete('msg');
    window.location.href = url.toString();
}

<?php if(isset($_GET['detail_id'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('detailModal');
    if (modal) {
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
});
<?php endif; ?>

function updateTotalJumlah() {
    const maxTotal = <?= $detail_aset['jumlah'] ?? 0 ?>;
    const baik = parseInt(document.querySelector('[name="jumlah_baik"]')?.value) || 0;
    const ringan = parseInt(document.querySelector('[name="jumlah_rusak_ringan"]')?.value) || 0;
    const berat = parseInt(document.querySelector('[name="jumlah_rusak_berat"]')?.value) || 0;
    const perbaikan = parseInt(document.querySelector('[name="jumlah_perbaikan"]')?.value) || 0;
    
    const total = baik + ringan + berat + perbaikan;
    document.getElementById('totalJumlah').textContent = total;
    
    const statusEl = document.getElementById('totalStatus');
    if (statusEl) {
        if (total === 0) {
            statusEl.textContent = 'Belum diinput';
            statusEl.className = 'text-xs font-semibold px-2 py-1 rounded-full bg-gray-200 text-gray-600';
        } else if (total < maxTotal) {
            statusEl.textContent = `Kurang ${maxTotal - total}`;
            statusEl.className = 'text-xs font-semibold px-2 py-1 rounded-full bg-amber-100 text-amber-700';
        } else if (total === maxTotal) {
            statusEl.textContent = '✓ Lengkap';
            statusEl.className = 'text-xs font-semibold px-2 py-1 rounded-full bg-emerald-100 text-emerald-700';
        } else {
            statusEl.textContent = '⚠️ Berlebih!';
            statusEl.className = 'text-xs font-semibold px-2 py-1 rounded-full bg-red-100 text-red-700';
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('formKondisi');
    if (form) {
        form.addEventListener('submit', function(e) {
            const maxTotal = <?= $detail_aset['jumlah'] ?? 0 ?>;
            const baik = parseInt(document.querySelector('[name="jumlah_baik"]')?.value) || 0;
            const ringan = parseInt(document.querySelector('[name="jumlah_rusak_ringan"]')?.value) || 0;
            const berat = parseInt(document.querySelector('[name="jumlah_rusak_berat"]')?.value) || 0;
            const perbaikan = parseInt(document.querySelector('[name="jumlah_perbaikan"]')?.value) || 0;
            
            const total = baik + ringan + berat + perbaikan;
            
            if (total === 0) {
                e.preventDefault();
                showToast('Minimal isi salah satu kondisi!', 'error');
                return false;
            }
            
            if (total > maxTotal) {
                e.preventDefault();
                showToast(`Total input (${total}) melebihi jumlah aset (${maxTotal})!`, 'error');
                return false;
            }
        });
    }
    
    updateTotalJumlah();
});

function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    const colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-amber-500' };
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    
    toast.className = `${colors[type]} text-white px-5 py-3 rounded-lg shadow-2xl flex items-center gap-3 min-w-[280px] animate-slide-in-left`;
    toast.innerHTML = `
        <i class="fas ${icons[type]} text-xl"></i>
        <span class="text-sm font-medium flex-1">${message}</span>
        <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

const urlParams = new URLSearchParams(window.location.search);
const action = urlParams.get('action');

if (action === 'inserted') {
    showToast('✓ Kondisi aset berhasil ditambahkan!', 'success');
} else if (action === 'updated') {
    showToast('✓ Kondisi aset berhasil diperbarui!', 'success');
} else if (action === 'error') {
    const msg = urlParams.get('msg') || 'Gagal menyimpan kondisi';
    showToast('❌ ' + msg, 'error');
} else if (action === 'empty') {
    showToast('⚠️ Minimal isi salah satu kondisi!', 'warning');
}

function kondisiApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        showHealthReport() {
            const total = <?= $total_aset_inventaris ?>;
            const dicek = <?= $total_aset_dicek ?>;
            const belum = <?= $total_belum_dicek ?>;
            const baik = <?= $stat_baik ?>;
            const ringan = <?= $stat_rusak_ringan ?>;
            const berat = <?= $stat_rusak_berat ?>;
            const perbaikan = <?= $stat_perbaikan ?>;
            const totalUnit = <?= $total_unit_checked ?>;
            const healthPercent = <?= $health_percent ?>;
            
            Swal.fire({
                title: '📊 Laporan Kesehatan Aset',
                html: `
                    <div class="text-left space-y-4 mt-4">
                        <div class="p-4 bg-gradient-to-br from-primary/10 to-primary/5 dark:from-primary/20 dark:to-primary/10 rounded-lg border border-primary/20">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-bold text-gray-800 dark:text-white">Health Score</span>
                                <span class="text-2xl font-bold ${healthPercent >= 80 ? 'text-emerald-600' : (healthPercent >= 60 ? 'text-amber-600' : (healthPercent >= 40 ? 'text-orange-600' : 'text-red-600'))}">${healthPercent}%</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2">
                                <div class="h-full rounded-full ${healthPercent >= 80 ? 'bg-emerald-500' : (healthPercent >= 60 ? 'bg-amber-500' : (healthPercent >= 40 ? 'bg-orange-500' : 'bg-red-500'))}" style="width: ${healthPercent}%"></div>
                            </div>
                            <p class="text-xs text-gray-600 dark:text-gray-400 mt-2">
                                Rumus: (${baik.toLocaleString()} unit baik / ${totalUnit.toLocaleString()} unit dicek) × 100
                            </p>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-center">
                                <i class="fas fa-check-circle text-emerald-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">${baik.toLocaleString()}</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400">Unit Baik</p>
                            </div>
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center">
                                <i class="fas fa-exclamation-triangle text-amber-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">${ringan.toLocaleString()}</p>
                                <p class="text-xs text-amber-600 dark:text-amber-400">Rusak Ringan</p>
                            </div>
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                                <i class="fas fa-times-circle text-red-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-red-700 dark:text-red-300">${berat.toLocaleString()}</p>
                                <p class="text-xs text-red-600 dark:text-red-400">Rusak Berat</p>
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                                <i class="fas fa-wrench text-blue-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">${perbaikan.toLocaleString()}</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400">Perbaikan</p>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 mb-2">Ringkasan</p>
                            <div class="space-y-1 text-xs">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Total jenis aset</span>
                                    <span class="font-bold">${total}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Aset sudah dicek</span>
                                    <span class="font-bold text-emerald-600">${dicek} (${total > 0 ? Math.round((dicek/total)*100) : 0}%)</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-300">Aset belum dicek</span>
                                    <span class="font-bold text-amber-600">${belum}</span>
                                </div>
                                <div class="flex justify-between pt-1 border-t border-gray-200 dark:border-gray-600">
                                    <span class="text-gray-600 dark:text-gray-300">Total unit dicek</span>
                                    <span class="font-bold text-primary">${totalUnit.toLocaleString()} unit</span>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                confirmButtonColor: '#1a365d',
                confirmButtonText: 'Tutup',
                width: '550px'
            });
        },
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        },
        
        confirmLogout() {
            Swal.fire({
                title: 'Logout?',
                text: 'Apakah Anda yakin ingin keluar dari sistem?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e53e3e',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Logout',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'logout.php';
            });
        }
    };
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('detailModal');
        if (modal && modal.style.display === 'flex') {
            closeDetailModal();
        }
    }
});

document.getElementById('detailModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeDetailModal();
    }
});
</script>

</body>
</html>