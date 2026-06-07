<?php
require_once 'config.php';

// Cek notifikasi arsip bulanan
$notif_arsip = cek_waktu_arsip();

// Proses tunda notifikasi
if(isset($_GET['tunda_notif'])) {
    $bulan = intval($_GET['bulan'] ?? $notif_arsip['bulan']);
    $tahun = intval($_GET['tahun'] ?? $notif_arsip['tahun']);
    update_status_notifikasi_arsip($bulan, $tahun, 'dismissed');
    header("Location: dashboard.php");
    exit;
}

// config already loaded above
$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ FILTER SUMBER PENGADAAN
$filter_sumber = isset($_GET['sumber']) ? mysqli_real_escape_string($conn, $_GET['sumber']) : '';

// ✅ STATISTIK BERBASIS UNIT/KUANTITAS (SUM dari kolom jumlah)
// Sama seperti kondisi_aset.php

// Total unit semua aset (SUM jumlah, bukan COUNT record)
$total_unit = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris")->fetch_assoc()['total'];
$total_record = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];

// Total unit per sumber pengadaan (SUM jumlah)
$total_pemerintah = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'Pemerintah'")->fetch_assoc()['total'];
$total_sekolah = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'Sekolah'")->fetch_assoc()['total'];
$total_bos = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'BOS'")->fetch_assoc()['total'];
$total_dak = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'DAK'")->fetch_assoc()['total'];
$total_apbd = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'APBD'")->fetch_assoc()['total'];

// Total nilai
$total_nilai = mysqli_query($conn, "SELECT COALESCE(SUM(total), 0) as total FROM inventaris")->fetch_assoc()['total'];

// ✅ PERHITUNGAN PERSENTASE BERDASARKAN UNIT
// Rumus: (unit_sumber / total_unit_semua) × 100
$persen_pemerintah = $total_unit > 0 ? round(($total_pemerintah / $total_unit) * 100) : 0;
$persen_sekolah = $total_unit > 0 ? round(($total_sekolah / $total_unit) * 100) : 0;
$persen_bos = $total_unit > 0 ? round(($total_bos / $total_unit) * 100) : 0;
$persen_dak = $total_unit > 0 ? round(($total_dak / $total_unit) * 100) : 0;
$persen_apbd = $total_unit > 0 ? round(($total_apbd / $total_unit) * 100) : 0;

// Rata-rata nilai per unit
$rata_rata_nilai = $total_unit > 0 ? round($total_nilai / $total_unit) : 0;

// Jumlah sumber yang aktif (punya unit > 0)
$sumber_aktif = 0;
if($total_pemerintah > 0) $sumber_aktif++;
if($total_sekolah > 0) $sumber_aktif++;
if($total_bos > 0) $sumber_aktif++;
if($total_dak > 0) $sumber_aktif++;
if($total_apbd > 0) $sumber_aktif++;

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search & Filter WHERE clause
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_clauses = [];

if($search) {
    $where_clauses[] = "(spesifikasi_nama_barang LIKE '%$search%' OR nama_barang_108 LIKE '%$search%')";
}

if($filter_sumber && $filter_sumber !== 'all') {
    $where_clauses[] = "sumber_pengadaan = '$filter_sumber'";
}

$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$result = mysqli_query($conn, "SELECT * FROM inventaris $where ORDER BY created_at DESC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Build query string untuk pagination
$query_params = [];
if($search) $query_params['search'] = $search;
if($filter_sumber) $query_params['sumber'] = $filter_sumber;
$query_string = http_build_query($query_params);

// Helper function untuk format angka besar
function formatUnit($angka) {
    if($angka >= 1000000) {
        return round($angka / 1000000, 1) . 'M';
    } elseif($angka >= 1000) {
        return round($angka / 1000, 1) . 'K';
    }
    return number_format($angka);
}
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventaris Sekolah</title>
    
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
                    fontFamily: { sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'] },
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
        .stagger-item:nth-child(1) { animation-delay: 0.05s; }
        .stagger-item:nth-child(2) { animation-delay: 0.1s; }
        .stagger-item:nth-child(3) { animation-delay: 0.15s; }
        .stagger-item:nth-child(4) { animation-delay: 0.2s; }
        .stagger-item:nth-child(5) { animation-delay: 0.25s; }
        
        .filter-pill.active {
            background: linear-gradient(135deg, #1a365d, #2c5282);
            color: white;
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
        }
        
        .progress-bar-fill {
            transition: width 1s ease-out;
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="dashboardApp()">
    
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
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard Inventaris</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        <i class="far fa-calendar-alt mr-1"></i>
                        <?= date('l, d F Y') ?>
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                
                <button @click="showNotifications()" 
                        class="relative p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas fa-bell text-gray-600 dark:text-gray-300"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                </button>
                
                <?php if(canCreate()): ?>
                <a href="tambah.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle"></i>
                    <span class="text-sm font-medium">Tambah Aset</span>
                </a>
                <?php else: ?>
                <div class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-400 text-white rounded-lg cursor-not-allowed opacity-60" title="Hanya admin yang dapat menambah aset">
                    <i class="fas fa-lock"></i>
                    <span class="text-sm font-medium">View Only</span>
                </div>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">

            <!-- Flash Message -->
            <?= showFlashMessage() ?>

            <?php if(isKepsek()): ?>
            <!-- Info Badge untuk Kepala Sekolah -->
            <div class="bg-gradient-to-r from-blue-500 to-cyan-500 rounded-xl p-4 text-white flex items-center gap-3 shadow-lg">
                <div class="p-3 bg-white/20 backdrop-blur rounded-lg">
                    <i class="fas fa-user-tie text-2xl"></i>
                </div>
                <div class="flex-1">
                    <h3 class="font-bold text-lg">Mode View Only</h3>
                    <p class="text-sm text-white/90">Anda login sebagai <strong>Kepala Sekolah</strong>. Hanya dapat melihat data, tidak dapat menambah, mengedit, atau menghapus data.</p>
                </div>
                <i class="fas fa-eye text-3xl opacity-50"></i>
            </div>
            <?php endif; ?>
            
            <!-- ✅ SECTION 1: OVERVIEW CARDS (3 Card Besar) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 lg:gap-6">
                
                <!-- Card 1: Total Unit Aset -->
                <div class="stagger-item group relative bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden text-white hover:-translate-y-1">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
                    <div class="relative p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                                <i class="fas fa-cubes text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 bg-white/20 backdrop-blur rounded-full">
                                <?= $total_record ?> Jenis
                            </span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-white/80 font-semibold mb-1">Total Unit Aset</p>
                        <h3 class="text-4xl font-bold mb-2"><?= number_format($total_unit) ?></h3>
                        <p class="text-xs text-white/70">
                            <i class="fas fa-chart-line mr-1"></i>
                            <?= $total_record ?> jenis aset tercatat
                        </p>
                    </div>
                </div>
                
                <!-- Card 2: Total Nilai -->
                <div class="stagger-item group relative bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden text-white hover:-translate-y-1">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
                    <div class="relative p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                                <i class="fas fa-coins text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 bg-white/20 backdrop-blur rounded-full">
                                <i class="fas fa-calculator mr-1"></i>SUM
                            </span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-white/80 font-semibold mb-1">Total Nilai</p>
                        <h3 class="text-3xl font-bold mb-2"><?= formatRupiah($total_nilai) ?></h3>
                        <p class="text-xs text-white/70">
                            <i class="fas fa-chart-bar mr-1"></i>
                            Rata-rata: <?= formatRupiah($rata_rata_nilai) ?>/unit
                        </p>
                    </div>
                </div>
                
                <!-- Card 3: Sumber Pengadaan Aktif -->
                <div class="stagger-item group relative bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl shadow-lg hover:shadow-2xl transition-all duration-300 overflow-hidden text-white hover:-translate-y-1">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16"></div>
                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/10 rounded-full translate-y-12 -translate-x-12"></div>
                    <div class="relative p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                                <i class="fas fa-handshake text-2xl"></i>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 bg-white/20 backdrop-blur rounded-full">
                                <?= $sumber_aktif ?>/5 Aktif
                            </span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-white/80 font-semibold mb-1">Sumber Pengadaan</p>
                        <h3 class="text-4xl font-bold mb-2"><?= $sumber_aktif ?></h3>
                        <p class="text-xs text-white/70">
                            <i class="fas fa-check-circle mr-1"></i>
                            Sumber yang memiliki unit aset
                        </p>
                    </div>
                </div>
                
            </div>
            
            <!-- Widget Peminjaman Aktif (Opsional) -->
            <?php
            $peminjaman_aktif = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'dipinjam'")->fetch_assoc()['total'];
            $peminjaman_terlambat = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'terlambat' OR (status = 'dipinjam' AND tanggal_kembali_rencana < CURDATE())")->fetch_assoc()['total'];

            if($peminjaman_aktif > 0 || $peminjaman_terlambat > 0):
            ?>
            <div class="bg-gradient-to-r from-sky-500 to-blue-600 rounded-2xl shadow-lg p-5 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                            <i class="fas fa-hand-holding-heart text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Peminjaman Aset</h3>
                            <p class="text-sm text-white/80">Monitoring peminjaman aktif</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="text-center px-4 py-2 bg-white/15 backdrop-blur rounded-lg">
                            <p class="text-2xl font-bold"><?= $peminjaman_aktif ?></p>
                            <p class="text-[10px] uppercase text-white/80">Aktif</p>
                        </div>
                        <?php if($peminjaman_terlambat > 0): ?>
                        <div class="text-center px-4 py-2 bg-red-500/30 backdrop-blur rounded-lg animate-pulse">
                            <p class="text-2xl font-bold"><?= $peminjaman_terlambat ?></p>
                            <p class="text-[10px] uppercase text-white/80">Terlambat</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="peminjaman.php" class="mt-3 inline-flex items-center gap-2 text-sm text-white/90 hover:text-white">
                    <span>Lihat Detail</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endif; ?>

            <!-- ✅ SECTION 2: BREAKDOWN SUMBER PENGADAAN (5 Card dengan Progress Bar) -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <!-- Section Header -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-chart-pie text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Breakdown Sumber Pengadaan</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Distribusi unit aset berdasarkan sumber pengadaan</p>
                            </div>
                        </div>
                        <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fas fa-info-circle"></i>
                            <span>Total: <strong class="text-primary"><?= number_format($total_unit) ?></strong> unit</span>
                        </div>
                    </div>
                </div>
                
                <!-- 5 Cards Grid -->
                <div class="p-5 lg:p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        
                        <?php 
                        $sumber_cards = [
                            [
                                'nama' => 'Pemerintah',
                                'total' => $total_pemerintah,
                                'persen' => $persen_pemerintah,
                                'icon' => 'fa-landmark',
                                'gradient' => 'from-primary to-primary-dark',
                                'bg_light' => 'bg-primary/10',
                                'text_color' => 'text-primary',
                                'bar_color' => 'bg-primary'
                            ],
                            [
                                'nama' => 'Sekolah',
                                'total' => $total_sekolah,
                                'persen' => $persen_sekolah,
                                'icon' => 'fa-school',
                                'gradient' => 'from-amber-500 to-amber-600',
                                'bg_light' => 'bg-amber-100',
                                'text_color' => 'text-amber-700',
                                'bar_color' => 'bg-amber-500'
                            ],
                            [
                                'nama' => 'BOS',
                                'total' => $total_bos,
                                'persen' => $persen_bos,
                                'icon' => 'fa-money-bill-wave',
                                'gradient' => 'from-emerald-500 to-emerald-600',
                                'bg_light' => 'bg-emerald-100',
                                'text_color' => 'text-emerald-700',
                                'bar_color' => 'bg-emerald-500'
                            ],
                            [
                                'nama' => 'DAK',
                                'total' => $total_dak,
                                'persen' => $persen_dak,
                                'icon' => 'fa-building-columns',
                                'gradient' => 'from-blue-500 to-cyan-600',
                                'bg_light' => 'bg-blue-100',
                                'text_color' => 'text-blue-700',
                                'bar_color' => 'bg-blue-500'
                            ],
                            [
                                'nama' => 'APBD',
                                'total' => $total_apbd,
                                'persen' => $persen_apbd,
                                'icon' => 'fa-landmark-flag',
                                'gradient' => 'from-rose-500 to-pink-600',
                                'bg_light' => 'bg-rose-100',
                                'text_color' => 'text-rose-700',
                                'bar_color' => 'bg-rose-500'
                            ],
                        ];
                        
                        foreach($sumber_cards as $idx => $card): ?>
                        <div class="stagger-item group relative bg-gradient-to-br <?= $card['bg_light'] ?> dark:bg-gray-700/50 rounded-xl p-4 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition-all duration-300 hover:-translate-y-1">
                            
                            <!-- Icon & Percentage -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="p-2.5 bg-gradient-to-br <?= $card['gradient'] ?> rounded-lg shadow-md">
                                    <i class="fas <?= $card['icon'] ?> text-white text-sm"></i>
                                </div>
                                <span class="text-xs font-bold px-2 py-1 bg-white dark:bg-gray-800 rounded-full shadow-sm <?= $card['text_color'] ?>">
                                    <?= $card['persen'] ?>%
                                </span>
                            </div>
                            
                            <!-- Title & Count (Unit) -->
                            <p class="text-xs uppercase tracking-wider text-gray-600 dark:text-gray-400 font-semibold mb-1">
                                <?= $card['nama'] ?>
                            </p>
                            <h4 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">
                                <?= number_format($card['total']) ?>
                            </h4>
                            <p class="text-[10px] text-gray-500 dark:text-gray-400 mb-3">unit aset</p>
                            
                            <!-- Progress Bar -->
                            <div class="relative">
                                <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                                    <div class="progress-bar-fill h-full rounded-full <?= $card['bar_color'] ?>" 
                                         style="width: <?= $card['persen'] ?>%"></div>
                                </div>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-1.5">
                                    <i class="fas fa-chart-line mr-1"></i>
                                    dari total unit
                                </p>
                            </div>
                            
                        </div>
                        <?php endforeach; ?>
                        
                    </div>
                    
                    <!-- Summary Bar -->
                    <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-400">
                            <div class="flex items-center gap-4 flex-wrap">
                                <?php foreach($sumber_cards as $card): ?>
                                <div class="flex items-center gap-1.5">
                                    <div class="w-2.5 h-2.5 rounded-full <?= $card['bar_color'] ?>"></div>
                                    <span><?= $card['nama'] ?>: <strong><?= $card['persen'] ?>%</strong></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="flex items-center gap-1.5 font-semibold text-primary dark:text-primary-light">
                                <i class="fas fa-check-circle"></i>
                                <span>Total: 100%</span>
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Data Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden"
                 x-data="tableApp()">
                
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col gap-4">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <div class="p-2 bg-primary/10 rounded-lg">
                                    <i class="fas fa-list text-primary text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">Data Inventaris</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        Menampilkan <span class="font-semibold text-primary"><?= $total_records ?></span> data
                                        <?php if($filter_sumber && $filter_sumber !== 'all'): ?>
                                            • Filter: <span class="font-semibold text-primary"><?= htmlspecialchars($filter_sumber) ?></span>
                                        <?php endif; ?>
                                        <?php if($search): ?>
                                            • Search: <span class="font-semibold text-primary">"<?= htmlspecialchars($search) ?>"</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <form method="GET" class="flex gap-2 w-full lg:w-auto" @submit="handleSearch($event)">
                                <?php if($filter_sumber): ?>
                                <input type="hidden" name="sumber" value="<?= htmlspecialchars($filter_sumber) ?>">
                                <?php endif; ?>
                                <div class="relative flex-1 lg:w-80">
                                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                    <input type="text" 
                                           name="search" 
                                           x-model="searchQuery"
                                           @input.debounce.500ms="handleSearch($event)"
                                           placeholder="Cari nama barang atau spesifikasi..." 
                                           value="<?= htmlspecialchars($search) ?>"
                                           class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                    <button x-show="searchQuery" @click="clearSearch()" type="button"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                        <i class="fas fa-times-circle"></i>
                                    </button>
                                </div>
                                <button type="submit" 
                                        class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm font-medium">
                                    <i class="fas fa-search"></i>
                                    <span class="hidden sm:inline">Cari</span>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Filter Toggle -->
                        <div class="flex items-center justify-between gap-2">
                            <button @click="showFilter = !showFilter" 
                                    type="button"
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all text-sm font-medium">
                                <i class="fas fa-filter" :class="showFilter ? 'text-primary' : ''"></i>
                                <span>Filter Sumber</span>
                                <?php if($filter_sumber && $filter_sumber !== 'all'): ?>
                                <span class="ml-1 px-2 py-0.5 bg-primary text-white text-[10px] rounded-full">
                                    <i class="fas fa-check"></i>
                                </span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-down ml-1 transition-transform" :class="showFilter ? 'rotate-180' : ''"></i>
                            </button>
                            
                            <?php if($filter_sumber || $search): ?>
                            <a href="dashboard.php" 
                               class="inline-flex items-center gap-2 px-4 py-2 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 rounded-lg transition-all text-sm font-medium">
                                <i class="fas fa-times"></i>
                                <span>Reset</span>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Filter Pills (Menampilkan Unit) -->
                        <div x-show="showFilter" 
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 -translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 -translate-y-2"
                             class="flex flex-wrap gap-2 pt-3 border-t border-gray-100 dark:border-gray-700"
                             style="display: none;">
                            
                            <a href="dashboard.php<?= $search ? '?search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= (!$filter_sumber || $filter_sumber === 'all') ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-layer-group"></i>
                                <span>Semua</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_unit) ?> unit</span>
                            </a>
                            
                            <a href="?sumber=Pemerintah<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_sumber === 'Pemerintah' ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-landmark"></i>
                                <span>Pemerintah</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_pemerintah) ?> unit</span>
                            </a>
                            
                            <a href="?sumber=Sekolah<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_sumber === 'Sekolah' ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-school"></i>
                                <span>Sekolah</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_sekolah) ?> unit</span>
                            </a>
                            
                            <a href="?sumber=BOS<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_sumber === 'BOS' ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-money-bill-wave"></i>
                                <span>BOS</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_bos) ?> unit</span>
                            </a>
                            
                            <a href="?sumber=DAK<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_sumber === 'DAK' ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-building-columns"></i>
                                <span>DAK</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_dak) ?> unit</span>
                            </a>
                            
                            <a href="?sumber=APBD<?= $search ? '&search=' . urlencode($search) : '' ?>" 
                               class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_sumber === 'APBD' ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                                <i class="fas fa-landmark-flag"></i>
                                <span>APBD</span>
                                <span class="ml-1 px-1.5 py-0.5 bg-white/20 rounded-full text-[10px]"><?= formatUnit($total_apbd) ?> unit</span>
                            </a>
                        </div>
                    </div>
                </div>
                
               
                <?php if($notif_arsip['show']): ?>
                <div class="relative bg-gradient-to-br from-red-500 via-orange-500 to-yellow-500 rounded-2xl shadow-2xl overflow-hidden animate-pulse-slow">
                    <!-- Animated Background -->
                    <div class="absolute inset-0 opacity-20">
                        <div class="absolute top-0 left-0 w-full h-full" 
                            style="background: radial-gradient(circle at 20% 50%, white 0%, transparent 50%), radial-gradient(circle at 80% 80%, white 0%, transparent 50%); animation: pulse 3s infinite;"></div>
                    </div>
                    
                    <div class="relative p-6 lg:p-8">
                        <div class="flex flex-col lg:flex-row lg:items-center gap-6">
                            <!-- Icon Besar -->
                            <div class="flex-shrink-0">
                                <div class="relative">
                                    <div class="w-24 h-24 bg-white/20 backdrop-blur rounded-full flex items-center justify-center animate-bounce">
                                        <i class="fas fa-bell text-5xl text-white"></i>
                                    </div>
                                    <div class="absolute -top-2 -right-2 w-8 h-8 bg-red-600 rounded-full flex items-center justify-center animate-ping">
                                        <i class="fas fa-exclamation text-white text-sm"></i>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 text-white">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-3 py-1 bg-white/20 backdrop-blur rounded-full text-xs font-bold uppercase tracking-wider">
                                        <i class="fas fa-calendar-alt mr-1"></i> Reminder Bulanan
                                    </span>
                                    <?php if($notif_arsip['status'] === 'dismissed'): ?>
                                    <span class="px-3 py-1 bg-blue-500 rounded-full text-xs font-bold uppercase tracking-wider">
                                        <i class="fas fa-eye-slash mr-1"></i> Ditunda
                                    </span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3 class="text-2xl lg:text-3xl font-bold mb-2">
                                    📋 Saatnya Buat Arsip <?= $notif_arsip['nama_bulan'] ?> <?= $notif_arsip['tahun'] ?>!
                                </h3>
                                
                                <p class="text-white/90 mb-4">
                                    Terdapat <strong class="text-yellow-300 text-lg"><?= $notif_arsip['jumlah_data'] ?> transaksi peminjaman</strong> 
                                    (<strong><?= $notif_arsip['total_unit'] ?> unit</strong>) pada bulan <?= $notif_arsip['nama_bulan'] ?> <?= $notif_arsip['tahun'] ?> 
                                    yang perlu diarsipkan untuk laporan operator.
                                </p>
                                
                                <!-- Info Box -->
                                <div class="bg-white/10 backdrop-blur rounded-lg p-3 mb-4 text-sm">
                                    <p class="text-white/90">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Notifikasi ini muncul karena hari ini sudah melewati <strong>tanggal <?= $notif_arsip['tanggal_setting'] ?></strong> 
                                        (tanggal setting arsip bulanan).
                                    </p>
                                </div>
                                
                                <!-- Action Buttons -->
                                <div class="flex flex-wrap gap-3">
                                    <a href="laporan_peminjaman.php" 
                                    class="px-6 py-3 bg-white text-orange-600 hover:bg-gray-100 rounded-lg shadow-lg hover:shadow-xl transition-all font-bold flex items-center gap-2">
                                        <i class="fas fa-archive"></i>
                                        <span>Buat Arsip Sekarang</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                    <a href="?tunda_notif=1&bulan=<?= $notif_arsip['bulan'] ?>&tahun=<?= $notif_arsip['tahun'] ?>" 
                                    onclick="return confirm('Tunda notifikasi ini? Anda bisa buat arsip nanti di menu Laporan Bulanan.')"
                                    class="px-6 py-3 bg-white/20 backdrop-blur hover:bg-white/30 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-semibold flex items-center gap-2">
                                        <i class="fas fa-clock"></i>
                                        <span>Tunda Nanti</span>
                                    </a>
                                    <?php if(isAdmin()): ?>
                                    <a href="pengaturan_arsip.php" 
                                    class="px-6 py-3 bg-white/20 backdrop-blur hover:bg-white/30 text-white rounded-lg shadow-lg hover:shadow-xl transition-all font-semibold flex items-center gap-2">
                                        <i class="fas fa-cog"></i>
                                        <span>Setting Tanggal</span>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Decorative Elements -->
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-16 translate-x-16"></div>
                    <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
                </div>
                <?php endif; ?>

                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">No</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Sumber</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Nama Barang</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Spesifikasi</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Jumlah</th>
                                <th class="px-4 py-4 text-right text-xs font-semibold uppercase tracking-wider">Harga Satuan</th>
                                <th class="px-4 py-4 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if($total_records == 0): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data ditemukan</p>
                                        <p class="text-sm text-gray-400">
                                            <?php if($filter_sumber || $search): ?>
                                                Coba ubah filter atau kata kunci pencarian
                                            <?php else: ?>
                                                Mulai dengan menambah aset pertama
                                            <?php endif; ?>
                                        </p>
                                        <?php if($filter_sumber || $search): ?>
                                        <a href="dashboard.php" class="mt-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all">
                                            <i class="fas fa-times mr-1"></i> Reset Filter
                                        </a>
                                        <?php elseif(canCreate()): ?>
                                        <a href="tambah.php" class="mt-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all">
                                            <i class="fas fa-plus mr-1"></i> Tambah Aset
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else: 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $badgeClass = match($row['sumber_pengadaan']) {
                                        'Pemerintah' => 'bg-primary/10 text-primary border-primary/20',
                                        'Sekolah' => 'bg-amber-100 text-amber-700 border-amber-200',
                                        'BOS' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                        'DAK' => 'bg-blue-100 text-blue-700 border-blue-200',
                                        'APBD' => 'bg-rose-100 text-rose-700 border-rose-200',
                                        default => 'bg-gray-100 text-gray-700 border-gray-200'
                                    };
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">
                                <td class="px-4 py-4 text-sm font-medium text-gray-600 dark:text-gray-300"><?= $no++ ?></td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold border <?= $badgeClass ?>">
                                        <i class="fas fa-circle text-[6px] mr-1.5 opacity-60"></i>
                                        <?= htmlspecialchars($row['sumber_pengadaan']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">
                                        <?= htmlspecialchars($row['nama_barang_108']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" 
                                         title="<?= htmlspecialchars($row['spesifikasi_nama_barang']) ?>">
                                        <?= htmlspecialchars(substr($row['spesifikasi_nama_barang'], 0, 50)) ?><?= strlen($row['spesifikasi_nama_barang']) > 50 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        <?= $row['jumlah'] ?>
                                        <span class="text-xs text-gray-500"><?= $row['satuan'] ?></span>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-right text-sm text-gray-700 dark:text-gray-200 font-medium">
                                    <?= formatRupiah($row['harga_satuan']) ?>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <span class="text-sm font-bold text-primary dark:text-primary-light">
                                        <?= formatRupiah($row['total']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Tombol Detail - SEMUA ROLE -->
                                        <button onclick="showDetail(<?= $row['id'] ?>)" 
                                                class="p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                                title="Detail">
                                            <i class="fas fa-eye text-sm"></i>
                                        </button>
                                        
                                        <!-- Tombol Edit - HANYA ADMIN -->
                                        <?php if(canUpdate()): ?>
                                        <a href="edit.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-amber-100 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <!-- Tombol Hapus - HANYA ADMIN -->
                                        <?php if(canDelete()): ?>
                                        <button @click="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_barang_108'])) ?>')"
                                                class="p-2 bg-red-100 hover:bg-red-500 text-red-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                                title="Hapus">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="p-4 lg:p-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Halaman <span class="font-semibold text-primary"><?= $page ?></span> 
                            dari <span class="font-semibold"><?= $total_pages ?></span>
                            <?php if($filter_sumber || $search): ?>
                                <span class="text-xs">(Total: <?= $total_records ?> data)</span>
                            <?php endif; ?>
                        </p>
                        <nav class="flex items-center gap-1">
                            <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&<?= $query_string ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if($start_page > 1): ?>
                                <a href="?page=1&<?= $query_string ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">1</a>
                                <?php if($start_page > 2): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?>&<?= $query_string ?>" 
                                   class="px-3 py-2 text-sm rounded-lg transition-all <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-primary hover:text-white hover:border-primary' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($end_page < $total_pages): ?>
                                <?php if($end_page < $total_pages - 1): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>&<?= $query_string ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all"><?= $total_pages ?></a>
                            <?php endif; ?>
                            
                            <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&<?= $query_string ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php endif; ?>
                        </nav>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </main>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function dashboardApp() {
    return {
        init() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'deleted') {
                this.showToast('Data berhasil dihapus!', 'success');
            } else if (urlParams.get('action') === 'added') {
                this.showToast('Data berhasil ditambahkan!', 'success');
            } else if (urlParams.get('action') === 'updated') {
                this.showToast('Data berhasil diperbarui!', 'success');
            }
        },
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
            this.showToast(this.darkMode ? 'Mode gelap diaktifkan' : 'Mode terang diaktifkan', 'info');
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
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'logout.php';
            });
        },
        
        showNotifications() {
            Swal.fire({
                title: 'Notifikasi',
                html: `
                    <div class="text-left space-y-3 mt-4">
                        <div class="p-3 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                            <p class="text-sm font-semibold text-blue-900">Selamat Datang!</p>
                            <p class="text-xs text-blue-700 mt-1">Sistem inventaris siap digunakan.</p>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-lg border-l-4 border-amber-500">
                            <p class="text-sm font-semibold text-amber-900">Reminder</p>
                            <p class="text-xs text-amber-700 mt-1">Periksa kondisi aset secara berkala.</p>
                        </div>
                    </div>
                `,
                confirmButtonColor: '#1a365d',
                confirmButtonText: 'Tutup'
            });
        },
        
        showToast(message, type = 'info') {
            const colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-amber-500' };
            const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
            
            const toast = document.createElement('div');
            toast.className = `${colors[type]} text-white px-5 py-3 rounded-lg shadow-2xl flex items-center gap-3 min-w-[280px] animate-slide-in-left`;
            toast.innerHTML = `
                <i class="fas ${icons[type]} text-xl"></i>
                <span class="text-sm font-medium flex-1">${message}</span>
                <button onclick="this.parentElement.remove()" class="text-white/80 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            `;
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }
    };
}

function tableApp() {
    return {
        showFilter: false,
        searchQuery: '<?= htmlspecialchars($search) ?>',
        
        handleSearch(event) {},
        
        clearSearch() {
            this.searchQuery = '';
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.delete('search');
            window.location.href = currentUrl.toString();
        },
        
        confirmDelete(id, name) {
            Swal.fire({
                title: 'Hapus Data?',
                html: `
                    <p class="text-gray-600">Apakah Anda yakin ingin menghapus:</p>
                    <p class="font-bold text-primary mt-2">${name}</p>
                    <p class="text-xs text-red-500 mt-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Tindakan ini tidak dapat dibatalkan!
                    </p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e53e3e',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    setTimeout(() => {
                        window.location.href = `hapus.php?id=${id}`;
                    }, 500);
                }
            });
        }
    };
}

// ✅ FUNGSI SHOW DETAIL
function showDetail(id) {
    Swal.fire({
        title: 'Memuat detail...',
        html: '<i class="fas fa-spinner fa-spin text-3xl text-primary"></i>',
        showConfirmButton: false,
        allowOutsideClick: false,
        allowEscapeKey: false
    });
    
    fetch(`api_detail_aset.php?id=${id}`)
        .then(response => response.json())
        .then(result => {
            if (!result.success) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: result.message || 'Gagal memuat data',
                    confirmButtonColor: '#1a365d'
                });
                return;
            }
            
            const d = result.data;
            const formatRupiah = (num) => 'Rp ' + parseInt(num || 0).toLocaleString('id-ID');
            const formatDate = (date) => {
                if (!date || date === '0000-00-00' || date === 'NULL') return '-';
                const d = new Date(date);
                return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
            };
            
            const kondisiColors = {
                'Baik': { bg: 'bg-emerald-100', text: 'text-emerald-700', icon: 'fa-check-circle' },
                'Rusak Ringan': { bg: 'bg-amber-100', text: 'text-amber-700', icon: 'fa-exclamation-triangle' },
                'Rusak Berat': { bg: 'bg-red-100', text: 'text-red-700', icon: 'fa-times-circle' },
                'Dalam Perbaikan': { bg: 'bg-blue-100', text: 'text-blue-700', icon: 'fa-wrench' },
            };
            const kondisiStyle = kondisiColors[d.kondisi_terkini] || { bg: 'bg-gray-100', text: 'text-gray-500', icon: 'fa-question-circle' };
            
            const sumberColors = {
                'Pemerintah': 'bg-primary/10 text-primary',
                'Sekolah': 'bg-amber-100 text-amber-700',
                'BOS': 'bg-emerald-100 text-emerald-700',
                'DAK': 'bg-blue-100 text-blue-700',
                'APBD': 'bg-rose-100 text-rose-700',
            };
            const sumberStyle = sumberColors[d.sumber_pengadaan] || 'bg-gray-100 text-gray-700';
            
            const html = `
                <div class="text-left space-y-4 max-h-[65vh] overflow-y-auto pr-2 custom-modal-scroll">
                    <div class="bg-gradient-to-br from-primary to-primary-dark rounded-xl p-4 text-white">
                        <div class="flex items-start gap-3">
                            <div class="p-2.5 bg-white/20 backdrop-blur rounded-lg flex-shrink-0">
                                <i class="fas fa-box text-xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-bold text-lg leading-tight mb-1">${d.nama_barang_108 || '-'}</h3>
                                <p class="text-xs text-white/80 line-clamp-2">${d.spesifikasi_nama_barang || '-'}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-3">
                            <span class="px-2 py-1 bg-white/20 backdrop-blur rounded-full text-[10px] font-semibold">
                                <i class="fas fa-tag mr-1"></i>${d.kategori_id || 'Umum'}
                            </span>
                            <span class="px-2 py-1 bg-white/20 backdrop-blur rounded-full text-[10px] font-semibold ${sumberStyle}">
                                <i class="fas fa-hand-holding-usd mr-1"></i>${d.sumber_pengadaan || '-'}
                            </span>
                            <span class="px-2 py-1 ${kondisiStyle.bg} ${kondisiStyle.text} rounded-full text-[10px] font-semibold">
                                <i class="fas ${kondisiStyle.icon} mr-1"></i>${d.kondisi_terkini || 'Belum Dicek'}
                            </span>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2 bg-blue-50 rounded-lg p-3 border border-blue-100">
                            <p class="text-[10px] uppercase tracking-wider text-blue-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-map-marker-alt"></i> Lokasi
                            </p>
                            <div class="space-y-1.5 text-xs">
                                <div class="flex justify-between"><span class="text-gray-600">Ruangan</span><span class="font-semibold text-gray-800">${d.nama_ruangan || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Kode Ruangan</span><span class="font-mono font-semibold text-gray-800">${d.kode_ruangan || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Gedung / Lantai</span><span class="font-semibold text-gray-800">${d.gedung || '-'} / Lantai ${d.lantai || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Kode Lokasi</span><span class="font-mono font-semibold text-gray-800">${d.kode_lokasi || '-'}</span></div>
                            </div>
                        </div>
                        
                        <div class="col-span-2 bg-emerald-50 rounded-lg p-3 border border-emerald-100">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-coins"></i> Nilai Aset
                            </p>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div><p class="text-[10px] text-gray-500">Jumlah</p><p class="font-bold text-gray-800">${d.jumlah || 0}</p><p class="text-[10px] text-gray-500">${d.satuan || '-'}</p></div>
                                <div><p class="text-[10px] text-gray-500">Harga Satuan</p><p class="font-bold text-gray-800 text-xs">${formatRupiah(d.harga_satuan)}</p></div>
                                <div class="bg-emerald-100 rounded p-1"><p class="text-[10px] text-emerald-700">Total</p><p class="font-bold text-emerald-700 text-xs">${formatRupiah(d.total)}</p></div>
                            </div>
                        </div>
                        
                        <div class="col-span-2 bg-amber-50 rounded-lg p-3 border border-amber-100">
                            <p class="text-[10px] uppercase tracking-wider text-amber-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-file-contract"></i> Informasi Pengadaan
                            </p>
                            <div class="space-y-1.5 text-xs">
                                <div class="flex justify-between"><span class="text-gray-600">No. Kontrak</span><span class="font-semibold text-gray-800">${d.no_dokumen_kontrak || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Tgl Kontrak</span><span class="font-semibold text-gray-800">${formatDate(d.tanggal_kontrak)}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">No. BAST</span><span class="font-semibold text-gray-800">${d.no_bast || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Tgl BAST</span><span class="font-semibold text-gray-800">${formatDate(d.tanggal_bast)}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Pihak ke-3</span><span class="font-semibold text-gray-800">${d.pihak_ke_3 || '-'}</span></div>
                            </div>
                        </div>
                        
                        <div class="col-span-2 bg-purple-50 rounded-lg p-3 border border-purple-100">
                            <p class="text-[10px] uppercase tracking-wider text-purple-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-user-tie"></i> Pejabat
                            </p>
                            <div class="space-y-1.5 text-xs">
                                <div class="flex justify-between"><span class="text-gray-600">PPK</span><span class="font-semibold text-gray-800">${d.nama_ppk || '-'}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Pengurus Barang</span><span class="font-semibold text-gray-800">${d.nama_pengurus_barang || '-'}</span></div>
                            </div>
                        </div>
                        
                        <div class="col-span-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <p class="text-[10px] uppercase tracking-wider text-gray-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-heart-pulse"></i> Kondisi Terkini
                            </p>
                            <div class="space-y-1.5 text-xs">
                                <div class="flex justify-between items-center">
                                    <span class="text-gray-600">Status</span>
                                    <span class="px-2 py-0.5 ${kondisiStyle.bg} ${kondisiStyle.text} rounded-full text-[10px] font-semibold">
                                        <i class="fas ${kondisiStyle.icon} mr-1"></i>${d.kondisi_terkini || 'Belum Dicek'}
                                    </span>
                                </div>
                                <div class="flex justify-between"><span class="text-gray-600">Terakhir Dicek</span><span class="font-semibold text-gray-800">${formatDate(d.tgl_cek_kondisi)}</span></div>
                                <div class="flex justify-between"><span class="text-gray-600">Petugas</span><span class="font-semibold text-gray-800">${d.petugas_cek || '-'}</span></div>
                                ${d.ket_kondisi ? `<div class="pt-1.5 border-t border-gray-200"><p class="text-gray-600 mb-1">Keterangan:</p><p class="text-gray-800 italic">${d.ket_kondisi}</p></div>` : ''}
                            </div>
                        </div>
                        
                        <div class="col-span-2 grid grid-cols-2 gap-2">
                            <div class="bg-indigo-50 rounded-lg p-2.5 border border-indigo-100 text-center">
                                <i class="fas fa-history text-indigo-500 text-lg mb-1"></i>
                                <p class="text-lg font-bold text-indigo-700">${d.riwayat_count}</p>
                                <p class="text-[10px] text-indigo-600">Riwayat Kondisi</p>
                            </div>
                            <div class="bg-cyan-50 rounded-lg p-2.5 border border-cyan-100 text-center">
                                <i class="fas fa-route text-cyan-500 text-lg mb-1"></i>
                                <p class="text-lg font-bold text-cyan-700">${d.tracking_count}</p>
                                <p class="text-[10px] text-cyan-600">Tracking Aset</p>
                            </div>
                        </div>
                        
                        ${(d.judul || d.pencipta || d.keterangan) ? `
                        <div class="col-span-2 bg-gray-50 rounded-lg p-3 border border-gray-200">
                            <p class="text-[10px] uppercase tracking-wider text-gray-600 font-bold mb-2 flex items-center gap-1">
                                <i class="fas fa-info-circle"></i> Informasi Tambahan
                            </p>
                            <div class="space-y-1.5 text-xs">
                                ${d.judul ? `<div class="flex justify-between"><span class="text-gray-600">Judul</span><span class="font-semibold text-gray-800">${d.judul}</span></div>` : ''}
                                ${d.pencipta ? `<div class="flex justify-between"><span class="text-gray-600">Pencipta</span><span class="font-semibold text-gray-800">${d.pencipta}</span></div>` : ''}
                                ${d.keterangan ? `<div class="pt-1.5 border-t border-gray-200"><p class="text-gray-600 mb-1">Keterangan:</p><p class="text-gray-800 italic">${d.keterangan}</p></div>` : ''}
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <style>
                    .custom-modal-scroll::-webkit-scrollbar { width: 6px; }
                    .custom-modal-scroll::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
                    .custom-modal-scroll::-webkit-scrollbar-thumb { background: #1a365d; border-radius: 10px; }
                </style>
            `;
            
            // Cek hak akses untuk tombol Edit & Hapus
            const canEdit = <?= canUpdate() ? 'true' : 'false' ?>;
            const canDelete = <?= canDelete() ? 'true' : 'false' ?>;
            
            Swal.fire({
                title: '<i class="fas fa-eye text-primary mr-2"></i> Detail Aset',
                html: html,
                width: '650px',
                showCancelButton: true,
                showConfirmButton: canEdit,
                confirmButtonText: '<i class="fas fa-edit mr-1"></i> Edit',
                cancelButtonText: '<i class="fas fa-times mr-1"></i> Tutup',
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#64748b',
                showDenyButton: canDelete,
                denyButtonText: '<i class="fas fa-trash mr-1"></i> Hapus',
                denyButtonColor: '#dc2626',
                customClass: { popup: 'rounded-2xl', title: 'text-primary font-bold' }
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `edit.php?id=${id}`;
                } else if (result.isDenied) {
                    Swal.fire({
                        title: 'Hapus Data?',
                        html: `
                            <p class="text-gray-600">Apakah Anda yakin ingin menghapus:</p>
                            <p class="font-bold text-primary mt-2">${d.nama_barang_108}</p>
                            <p class="text-xs text-red-500 mt-3">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                Tindakan ini tidak dapat dibatalkan!
                            </p>
                        `,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#e53e3e',
                        cancelButtonColor: '#718096',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal',
                        reverseButtons: true
                    }).then((confirmResult) => {
                        if (confirmResult.isConfirmed) {
                            Swal.fire({
                                title: 'Menghapus...',
                                allowOutsideClick: false,
                                didOpen: () => Swal.showLoading()
                            });
                            setTimeout(() => {
                                window.location.href = `hapus.php?id=${id}`;
                            }, 500);
                        }
                    });
                }
            });
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Gagal memuat data: ' + error.message,
                confirmButtonColor: '#1a365d'
            });
        });
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) searchInput.focus();
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        const app = document.querySelector('[x-data]').__x;
        if (app) app.$data.toggleDarkMode();
    }
});

document.addEventListener('DOMContentLoaded', () => {
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach((row, idx) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(10px)';
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease-out';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, idx * 30);
    });
});
</script>

</body>
</html>