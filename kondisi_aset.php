<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Update kondisi
if(isset($_POST['update_kondisi'])) {
    $inventaris_id = intval($_POST['inventaris_id']);
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
    $tanggal_cek = date('Y-m-d');

    $stmt = mysqli_prepare($conn, "INSERT INTO kondisi_aset (inventaris_id, kondisi, tanggal_cek, keterangan, petugas) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issss", $inventaris_id, $kondisi, $tanggal_cek, $keterangan, $petugas);
    
    if(mysqli_stmt_execute($stmt)) {
        header("Location: kondisi_aset.php?action=updated");
        exit;
    } else {
        header("Location: kondisi_aset.php?action=error");
        exit;
    }
}

// Filter kondisi
$filter = isset($_GET['filter']) ? mysqli_real_escape_string($conn, $_GET['filter']) : 'all';
$where = "";
if($filter != 'all') {
    $where = "WHERE ks.kondisi = '$filter'";
}

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
if($search) {
    if($where) {
        $where .= " AND (i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%')";
    } else {
        $where = "WHERE i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%'";
    }
}

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

// Statistik kondisi (optimized dengan single query)
$stats_query = "SELECT 
    SUM(CASE WHEN kondisi = 'Baik' THEN 1 ELSE 0 END) as baik,
    SUM(CASE WHEN kondisi = 'Rusak Ringan' THEN 1 ELSE 0 END) as rusak_ringan,
    SUM(CASE WHEN kondisi = 'Rusak Berat' THEN 1 ELSE 0 END) as rusak_berat,
    SUM(CASE WHEN kondisi = 'Dalam Perbaikan' THEN 1 ELSE 0 END) as perbaikan,
    COUNT(*) as total
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

$stat_baik = $stats['baik'] ?? 0;
$stat_rusak_ringan = $stats['rusak_ringan'] ?? 0;
$stat_rusak_berat = $stats['rusak_berat'] ?? 0;
$stat_perbaikan = $stats['perbaikan'] ?? 0;
$total_cek = $stats['total'] ?? 0;

// Hitung persentase kesehatan aset
$health_percent = $total_cek > 0 ? round(($stat_baik / $total_cek) * 100) : 0;
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
                        'slide-in-left': 'slideInLeft 0.3s ease-out',
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite'
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
        
        /* Condition badge pulse for critical */
        .badge-critical {
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="kondisiApp()">
    
    <!-- Mobile Overlay -->
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
    
    <!-- Sidebar -->
    <aside 
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed lg:translate-x-0 lg:static inset-y-0 left-0 z-50 w-64 bg-primary text-white flex flex-col shadow-2xl transition-transform duration-300 ease-in-out">
        
        <div class="p-6 text-center border-b border-white/10">
            <img src="assets/img/logo.png" 
                 onerror="this.src='https://ui-avatars.com/api/?name=SDN&background=ffffff&color=1a365d&size=120'"
                 class="w-24 h-24 rounded-full mx-auto mb-3 object-cover border-4 border-white/20 hover:scale-110 transition-transform duration-300"
                 alt="Logo">
            <h4 class="text-lg font-semibold text-white">Inventaris Sekolah</h4>
            <p class="text-xs text-gray-300 mt-1">SDN Curug 01</p>
        </div>
        
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <?php 
            $menus = [
                ['dashboard.php', 'fa-home', 'Dashboard'],
                ['ruangan.php', 'fa-door-open', 'Manajemen Ruangan'],
                ['kategori_aset.php', 'fa-tools', 'Kategori Aset'],
                ['tambah.php', 'fa-plus-circle', 'Tambah Aset'],
                ['kondisi_aset.php', 'fa-heart-pulse', 'Kondisi Aset'],
                ['tracking_aset.php', 'fa-route', 'Tracking Aset'],
                ['export_excel.php', 'fa-file-excel', 'Export Excel'],
            ];
            foreach($menus as $menu): ?>
            <a href="<?= $menu[0] ?>" 
               class="flex items-center px-4 py-3 rounded-lg text-sm transition-all duration-200 hover:bg-white/10 hover:pl-6 
                      <?= ($current_page == $menu[0]) ? 'bg-white text-primary font-semibold shadow-lg' : 'text-white/80' ?>">
                <i class="fas <?= $menu[1] ?> w-5 mr-3 text-center"></i>
                <span><?= $menu[2] ?></span>
                <?php if($current_page == $menu[0]): ?>
                    <span class="ml-auto w-2 h-2 bg-primary rounded-full animate-pulse"></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </nav>
        
        <div class="p-4 border-t border-white/10">
            <a href="logout.php" 
               @click.prevent="confirmLogout()"
               class="flex items-center px-4 py-3 rounded-lg text-sm text-white/80 hover:bg-red-500/20 hover:text-white transition-all">
                <i class="fas fa-sign-out-alt w-5 mr-3"></i>
                <span>Logout</span>
            </a>
        </div>
    </aside>
    
    <!-- Main Content -->
    <main class="flex-1 flex flex-col min-w-0 overflow-x-hidden">
        
        <!-- Top Bar -->
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
        
        <!-- Content Area -->
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
                                <p class="text-sm text-white/80">Tingkat kesehatan aset sekolah secara keseluruhan</p>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex items-end gap-2 mb-2">
                                <span class="text-5xl font-bold" x-text="healthPercent + '%'"><?= $health_percent ?>%</span>
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
                            <p class="text-xs text-white/70 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                <?= $stat_baik ?> dari <?= $total_cek ?> aset dalam kondisi baik
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/10 backdrop-blur rounded-xl p-4 text-center">
                            <i class="fas fa-calendar-check text-2xl mb-2 text-emerald-300"></i>
                            <p class="text-2xl font-bold"><?= $total_cek ?></p>
                            <p class="text-xs text-white/80">Aset Dicek</p>
                        </div>
                        <div class="bg-white/10 backdrop-blur rounded-xl p-4 text-center">
                            <i class="fas fa-clock text-2xl mb-2 text-blue-300"></i>
                            <p class="text-2xl font-bold"><?= date('d M') ?></p>
                            <p class="text-xs text-white/80">Update Terakhir</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <?php 
                $stats_cards = [
                    ['Baik', $stat_baik, 'fa-check-circle', 'from-emerald-500 to-emerald-600', 'bg-emerald-100 dark:bg-emerald-900/30', 'text-emerald-700 dark:text-emerald-300'],
                    ['Rusak Ringan', $stat_rusak_ringan, 'fa-exclamation-triangle', 'from-amber-500 to-amber-600', 'bg-amber-100 dark:bg-amber-900/30', 'text-amber-700 dark:text-amber-300'],
                    ['Rusak Berat', $stat_rusak_berat, 'fa-times-circle', 'from-red-500 to-red-600', 'bg-red-100 dark:bg-red-900/30', 'text-red-700 dark:text-red-300'],
                    ['Dalam Perbaikan', $stat_perbaikan, 'fa-wrench', 'from-blue-500 to-blue-600', 'bg-blue-100 dark:bg-blue-900/30', 'text-blue-700 dark:text-blue-300'],
                ];
                foreach($stats_cards as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1 cursor-pointer"
                     @click="filterByCondition('<?= $stat[0] ?>')">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-5 lg:p-6">
                        <div class="flex items-start justify-between mb-3">
                            <div class="p-3 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-lg"></i>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">
                                <?= $total_cek > 0 ? round(($stat[1] / $total_cek) * 100) : 0 ?>%
                            </span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-3xl font-bold text-gray-800 dark:text-white"><?= $stat[1] ?></h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                            <i class="fas fa-cube mr-1"></i>
                            Aset termonitor
                        </p>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Filter & Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <!-- Card Header -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-list-check text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Daftar Kondisi Aset</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Urutan: Kondisi kritis di prioritaskan
                                </p>
                            </div>
                        </div>
                        
                        <!-- Search & Filter -->
                        <form method="GET" class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                            <select name="filter" 
                                    @change="$el.form.submit()"
                                    class="px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                <option value="all" <?= $filter=='all'?'selected':'' ?>>Semua Kondisi</option>
                                <option value="Baik" <?= $filter=='Baik'?'selected':'' ?>>✅ Baik</option>
                                <option value="Rusak Ringan" <?= $filter=='Rusak Ringan'?'selected':'' ?>>⚠️ Rusak Ringan</option>
                                <option value="Rusak Berat" <?= $filter=='Rusak Berat'?'selected':'' ?>>❌ Rusak Berat</option>
                                <option value="Dalam Perbaikan" <?= $filter=='Dalam Perbaikan'?'selected':'' ?>>🔧 Dalam Perbaikan</option>
                            </select>
                            
                            <div class="relative flex-1 lg:w-80">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Cari nama/spesifikasi aset..." 
                                       value="<?= htmlspecialchars($search) ?>"
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                            </div>
                            
                            <button type="submit" 
                                    class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                <i class="fas fa-filter"></i>
                                <span>Filter</span>
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Table -->
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
                            <?php 
                            $no = 1;
                            while($row = mysqli_fetch_assoc($result)): 
                                $currentCondition = $row['kondisi_aset'] ?? 'Belum Dicek';
                                
                                // Determine badge styling
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
                                
                                $tanggal_cek = $row['last_tanggal_cek'] ?? $row['created_at'];
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
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                            <span><?= $tanggal_cek ? formatTanggal($tanggal_cek) : '-' ?></span>
                                        </div>
                                        <?php if($tanggal_cek): ?>
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
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <button @click="openUpdateModal(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_barang_108'])) ?>', '<?= $currentCondition ?>')"
                                                class="p-2 bg-amber-100 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                                title="Update Kondisi">
                                            <i class="fas fa-edit text-sm"></i>
                                        </button>
                                        <a href="riwayat_kondisi.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Riwayat Kondisi">
                                            <i class="fas fa-history text-sm"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Empty State -->
                <?php if(mysqli_num_rows($result) == 0): ?>
                <div class="p-12 text-center">
                    <div class="flex flex-col items-center gap-3">
                        <i class="fas fa-clipboard-check text-5xl text-gray-300 dark:text-gray-600"></i>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data ditemukan</p>
                        <p class="text-sm text-gray-400">Coba ubah filter atau kata kunci pencarian</p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
        </div>
    </main>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function kondisiApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        healthPercent: <?= $health_percent ?>,
        
        init() {
            // Check for URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'updated') {
                this.showToast('Kondisi aset berhasil diupdate!', 'success');
            } else if (urlParams.get('action') === 'error') {
                this.showToast('Gagal mengupdate kondisi!', 'error');
            }
            
            // Animate health percent
            this.animateHealth();
        },
        
        animateHealth() {
            const target = this.healthPercent;
            this.healthPercent = 0;
            const duration = 1500;
            const steps = 60;
            const increment = target / steps;
            let current = 0;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    this.healthPercent = target;
                    clearInterval(timer);
                } else {
                    this.healthPercent = Math.floor(current);
                }
            }, duration / steps);
        },
        
        filterByCondition(condition) {
            const url = new URL(window.location);
            url.searchParams.set('filter', condition);
            window.location.href = url.toString();
        },
        
        openUpdateModal(id, nama, currentCondition) {
            Swal.fire({
                title: 'Update Kondisi Aset',
                html: `
                    <div class="text-left space-y-4 mt-4">
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Nama Barang</p>
                            <p class="font-semibold text-gray-800 dark:text-white">${nama}</p>
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                <i class="fas fa-heart-pulse text-primary"></i>
                                Kondisi Aset <span class="text-red-500">*</span>
                            </label>
                            <div class="grid grid-cols-2 gap-2" id="condition-selector">
                                <button type="button" onclick="selectCondition('Baik', this)" 
                                        class="condition-btn p-3 border-2 rounded-lg text-sm font-medium transition-all flex flex-col items-center gap-1.5 ${currentCondition === 'Baik' ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 hover:border-emerald-500'}">
                                    <i class="fas fa-check-circle text-emerald-500 text-lg"></i>
                                    <span>Baik</span>
                                </button>
                                <button type="button" onclick="selectCondition('Rusak Ringan', this)"
                                        class="condition-btn p-3 border-2 rounded-lg text-sm font-medium transition-all flex flex-col items-center gap-1.5 ${currentCondition === 'Rusak Ringan' ? 'border-amber-500 bg-amber-50 text-amber-700' : 'border-gray-200 hover:border-amber-500'}">
                                    <i class="fas fa-exclamation-triangle text-amber-500 text-lg"></i>
                                    <span>Rusak Ringan</span>
                                </button>
                                <button type="button" onclick="selectCondition('Rusak Berat', this)"
                                        class="condition-btn p-3 border-2 rounded-lg text-sm font-medium transition-all flex flex-col items-center gap-1.5 ${currentCondition === 'Rusak Berat' ? 'border-red-500 bg-red-50 text-red-700' : 'border-gray-200 hover:border-red-500'}">
                                    <i class="fas fa-times-circle text-red-500 text-lg"></i>
                                    <span>Rusak Berat</span>
                                </button>
                                <button type="button" onclick="selectCondition('Dalam Perbaikan', this)"
                                        class="condition-btn p-3 border-2 rounded-lg text-sm font-medium transition-all flex flex-col items-center gap-1.5 ${currentCondition === 'Dalam Perbaikan' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 hover:border-blue-500'}">
                                    <i class="fas fa-wrench text-blue-500 text-lg"></i>
                                    <span>Dalam Perbaikan</span>
                                </button>
                            </div>
                            <input type="hidden" id="selected-kondisi" value="${currentCondition}">
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                <i class="fas fa-note-sticky text-primary"></i>
                                Keterangan
                            </label>
                            <textarea id="update-keterangan" rows="3" 
                                      class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm"
                                      placeholder="Deskripsi kondisi aset, kerusakan, dll..."></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#1a365d',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Simpan Update',
                cancelButtonText: 'Batal',
                width: '500px',
                preConfirm: () => {
                    const kondisi = document.getElementById('selected-kondisi').value;
                    const keterangan = document.getElementById('update-keterangan').value;
                    
                    if (!kondisi) {
                        Swal.showValidationMessage('Pilih kondisi aset terlebih dahulu');
                        return false;
                    }
                    
                    return { kondisi, keterangan };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create form and submit
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="inventaris_id" value="${id}">
                        <input type="hidden" name="kondisi" value="${result.value.kondisi}">
                        <input type="hidden" name="keterangan" value="${result.value.keterangan}">
                        <input type="hidden" name="update_kondisi" value="1">
                    `;
                    document.body.appendChild(form);
                    
                    Swal.fire({
                        title: 'Menyimpan...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    
                    setTimeout(() => form.submit(), 500);
                }
            });
        },
        
        showHealthReport() {
            const total = <?= $total_cek ?>;
            const baik = <?= $stat_baik ?>;
            const ringan = <?= $stat_rusak_ringan ?>;
            const berat = <?= $stat_rusak_berat ?>;
            const perbaikan = <?= $stat_perbaikan ?>;
            
            Swal.fire({
                title: '📊 Laporan Kesehatan Aset',
                html: `
                    <div class="text-left space-y-4 mt-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg text-center">
                                <i class="fas fa-check-circle text-emerald-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">${baik}</p>
                                <p class="text-xs text-emerald-600 dark:text-emerald-400">Baik (${total > 0 ? Math.round((baik/total)*100) : 0}%)</p>
                            </div>
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-center">
                                <i class="fas fa-exclamation-triangle text-amber-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">${ringan}</p>
                                <p class="text-xs text-amber-600 dark:text-amber-400">Rusak Ringan (${total > 0 ? Math.round((ringan/total)*100) : 0}%)</p>
                            </div>
                            <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded-lg text-center">
                                <i class="fas fa-times-circle text-red-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-red-700 dark:text-red-300">${berat}</p>
                                <p class="text-xs text-red-600 dark:text-red-400">Rusak Berat (${total > 0 ? Math.round((berat/total)*100) : 0}%)</p>
                            </div>
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-center">
                                <i class="fas fa-wrench text-blue-500 text-2xl mb-1"></i>
                                <p class="text-2xl font-bold text-blue-700 dark:text-blue-300">${perbaikan}</p>
                                <p class="text-xs text-blue-600 dark:text-blue-400">Perbaikan (${total > 0 ? Math.round((perbaikan/total)*100) : 0}%)</p>
                            </div>
                        </div>
                        
                        <div class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Rekomendasi</p>
                            ${berat > 0 ? `
                                <div class="flex items-start gap-2 text-xs text-red-700 dark:text-red-300 mb-1">
                                    <i class="fas fa-exclamation-circle mt-0.5"></i>
                                    <span><strong>${berat} aset</strong> perlu segera diganti/diperbaiki berat</span>
                                </div>
                            ` : ''}
                            ${ringan > 0 ? `
                                <div class="flex items-start gap-2 text-xs text-amber-700 dark:text-amber-300 mb-1">
                                    <i class="fas fa-exclamation-triangle mt-0.5"></i>
                                    <span><strong>${ringan} aset</strong> perlu perawatan rutin</span>
                                </div>
                            ` : ''}
                            ${perbaikan > 0 ? `
                                <div class="flex items-start gap-2 text-xs text-blue-700 dark:text-blue-300 mb-1">
                                    <i class="fas fa-wrench mt-0.5"></i>
                                    <span><strong>${perbaikan} aset</strong> sedang dalam proses perbaikan</span>
                                </div>
                            ` : ''}
                            ${berat === 0 && ringan === 0 && perbaikan === 0 ? `
                                <div class="flex items-start gap-2 text-xs text-emerald-700 dark:text-emerald-300">
                                    <i class="fas fa-check-circle mt-0.5"></i>
                                    <span>Semua aset dalam kondisi baik! Pertahankan perawatan rutin.</span>
                                </div>
                            ` : ''}
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
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) window.location.href = 'logout.php';
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

// Global function for SweetAlert condition selector
function selectCondition(condition, btn) {
    document.getElementById('selected-kondisi').value = condition;
    
    // Reset all buttons
    document.querySelectorAll('.condition-btn').forEach(b => {
        b.classList.remove('border-emerald-500', 'bg-emerald-50', 'text-emerald-700',
                           'border-amber-500', 'bg-amber-50', 'text-amber-700',
                           'border-red-500', 'bg-red-50', 'text-red-700',
                           'border-blue-500', 'bg-blue-50', 'text-blue-700');
        b.classList.add('border-gray-200');
    });
    
    // Highlight selected
    const colorMap = {
        'Baik': ['border-emerald-500', 'bg-emerald-50', 'text-emerald-700'],
        'Rusak Ringan': ['border-amber-500', 'bg-amber-50', 'text-amber-700'],
        'Rusak Berat': ['border-red-500', 'bg-red-50', 'text-red-700'],
        'Dalam Perbaikan': ['border-blue-500', 'bg-blue-50', 'text-blue-700']
    };
    
    btn.classList.remove('border-gray-200');
    colorMap[condition].forEach(cls => btn.classList.add(cls));
}

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) searchInput.focus();
    }
});

// Animate table rows
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