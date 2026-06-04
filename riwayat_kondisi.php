<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Validasi ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) {
    header("Location: kondisi_aset.php");
    exit;
}

// Ambil data aset
$aset = mysqli_query($conn, "SELECT i.*, r.nama_ruangan, r.kode_ruangan, r.gedung, r.lantai 
    FROM inventaris i 
    LEFT JOIN ruangan r ON i.ruangan_id = r.id 
    WHERE i.id = $id")->fetch_assoc();

if(!$aset) {
    header("Location: kondisi_aset.php");
    exit;
}

// Ambil riwayat kondisi
$riwayat = mysqli_query($conn, "SELECT * FROM kondisi_aset 
    WHERE inventaris_id = $id 
    ORDER BY created_at DESC");

// Statistik riwayat
$total_riwayat = mysqli_query($conn, "SELECT COUNT(*) as total FROM kondisi_aset WHERE inventaris_id = $id")->fetch_assoc()['total'];
$perubahan_count = mysqli_query($conn, "SELECT COUNT(DISTINCT kondisi) as total FROM kondisi_aset WHERE inventaris_id = $id")->fetch_assoc()['total'];

// Kondisi terkini
$kondisi_terkini = $aset['kondisi_aset'] ?? 'Belum Dicek';

// Badge style untuk kondisi
$kondisi_styles = [
    'Baik' => ['bg' => 'bg-emerald-500', 'gradient' => 'from-emerald-500 to-emerald-600', 'icon' => 'fa-check-circle'],
    'Rusak Ringan' => ['bg' => 'bg-amber-500', 'gradient' => 'from-amber-500 to-amber-600', 'icon' => 'fa-exclamation-triangle'],
    'Rusak Berat' => ['bg' => 'bg-red-500', 'gradient' => 'from-red-500 to-red-600', 'icon' => 'fa-times-circle'],
    'Dalam Perbaikan' => ['bg' => 'bg-blue-500', 'gradient' => 'from-blue-500 to-blue-600', 'icon' => 'fa-wrench'],
    'Belum Dicek' => ['bg' => 'bg-gray-500', 'gradient' => 'from-gray-500 to-gray-600', 'icon' => 'fa-question-circle'],
];

$current_style = $kondisi_styles[$kondisi_terkini] ?? $kondisi_styles['Belum Dicek'];

$current_page = 'kondisi_aset.php';
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false, viewMode: 'timeline' }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kondisi - <?= htmlspecialchars($aset['nama_barang_108']) ?></title>
    
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
        .stagger-item:nth-child(1) { animation-delay: 0.05s; }
        .stagger-item:nth-child(2) { animation-delay: 0.1s; }
        .stagger-item:nth-child(3) { animation-delay: 0.15s; }
        .stagger-item:nth-child(4) { animation-delay: 0.2s; }
        .stagger-item:nth-child(5) { animation-delay: 0.25s; }
        
        /* Timeline connector */
        .timeline-connector {
            position: absolute;
            left: 23px;
            top: 60px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #1a365d, #e2e8f0);
        }
        .dark .timeline-connector {
            background: linear-gradient(to bottom, #4a5568, #2d3748);
        }
        
        /* Timeline dot */
        .timeline-dot {
            position: relative;
            z-index: 2;
        }
        .timeline-dot::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: currentColor;
            opacity: 0.1;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.1; }
            50% { transform: translate(-50%, -50%) scale(1.2); opacity: 0.2; }
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen">
    
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
    
    <?php include 'sidebar.php'; ?>
    
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
                        <a href="kondisi_aset.php" class="hover:text-primary">Kondisi Aset</a>
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="text-primary font-semibold">Riwayat</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-history"></i>
                        <span>Riwayat Kondisi Aset</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                
                <a href="kondisi_aset.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all">
                    <i class="fas fa-arrow-left"></i>
                    <span class="text-sm font-medium">Kembali</span>
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Info Aset Card -->
            <div class="stagger-item bg-white dark:bg-gray-800 rounded-2xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="bg-gradient-to-r <?= $current_style['gradient'] ?> p-6 text-white relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-48 h-48 bg-white/10 rounded-full -translate-y-24 translate-x-24"></div>
                    <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/10 rounded-full translate-y-16 -translate-x-16"></div>
                    
                    <div class="relative flex flex-col lg:flex-row lg:items-center gap-6">
                        <div class="flex items-center gap-4 flex-1">
                            <div class="w-20 h-20 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-box text-3xl"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-2xl font-bold mb-1 truncate"><?= htmlspecialchars($aset['nama_barang_108']) ?></h3>
                                <p class="text-sm text-white/80 line-clamp-2"><?= htmlspecialchars($aset['spesifikasi_nama_barang'] ?? '-') ?></p>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            <div class="bg-white/20 backdrop-blur rounded-xl px-4 py-3 text-center min-w-[100px]">
                                <p class="text-[10px] uppercase tracking-wider text-white/70 mb-1">Kondisi</p>
                                <p class="font-bold flex items-center justify-center gap-1">
                                    <i class="fas <?= $current_style['icon'] ?>"></i>
                                    <span><?= $kondisi_terkini ?></span>
                                </p>
                            </div>
                            <div class="bg-white/20 backdrop-blur rounded-xl px-4 py-3 text-center min-w-[100px]">
                                <p class="text-[10px] uppercase tracking-wider text-white/70 mb-1">Total Nilai</p>
                                <p class="font-bold"><?= formatRupiah($aset['total']) ?></p>
                            </div>
                            <div class="bg-white/20 backdrop-blur rounded-xl px-4 py-3 text-center min-w-[100px]">
                                <p class="text-[10px] uppercase tracking-wider text-white/70 mb-1">Jumlah</p>
                                <p class="font-bold"><?= $aset['jumlah'] ?> <?= $aset['satuan'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detail Info -->
                <div class="p-5 lg:p-6 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <i class="fas fa-door-open text-blue-600 dark:text-blue-400"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Ruangan</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white truncate"><?= htmlspecialchars($aset['nama_ruangan'] ?? '-') ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                            <i class="fas fa-building text-purple-600 dark:text-purple-400"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Gedung</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white truncate"><?= htmlspecialchars($aset['gedung'] ?? '-') ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg">
                            <i class="fas fa-layer-group text-emerald-600 dark:text-emerald-400"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Lantai</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white">Lantai <?= $aset['lantai'] ?? '-' ?></p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded-lg">
                            <i class="fas fa-qrcode text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Kode Ruangan</p>
                            <p class="text-sm font-semibold text-gray-800 dark:text-white font-mono truncate"><?= htmlspecialchars($aset['kode_ruangan'] ?? '-') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Statistik Riwayat -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="stagger-item bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-5 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-gradient-to-br from-primary to-primary-dark rounded-lg text-white shadow-lg">
                            <i class="fas fa-history text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Total Riwayat</p>
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?= $total_riwayat ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stagger-item bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-5 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg text-white shadow-lg">
                            <i class="fas fa-exchange-alt text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Perubahan Kondisi</p>
                            <h3 class="text-2xl font-bold text-gray-800 dark:text-white"><?= $perubahan_count ?></h3>
                        </div>
                    </div>
                </div>
                
                <div class="stagger-item bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-5 hover:shadow-lg transition-all hover:-translate-y-1">
                    <div class="flex items-center gap-3">
                        <div class="p-3 bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-lg text-white shadow-lg">
                            <i class="fas fa-calendar-check text-lg"></i>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Terakhir Dicek</p>
                            <?php 
                            $last_cek = mysqli_query($conn, "SELECT tanggal_cek FROM kondisi_aset WHERE inventaris_id = $id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
                            $last_date = $last_cek['tanggal_cek'] ?? null;
                            ?>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">
                                <?= $last_date ? formatTanggal($last_date) : 'Belum Pernah' ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- View Toggle & Content -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <!-- Header with View Toggle -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-timeline text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Timeline Perubahan Kondisi</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Riwayat lengkap perubahan kondisi aset
                                </p>
                            </div>
                        </div>
                        
                        <!-- View Toggle -->
                        <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                            <button @click="viewMode = 'timeline'" 
                                    :class="viewMode === 'timeline' ? 'bg-white dark:bg-gray-600 text-primary dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
                                    class="px-4 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2">
                                <i class="fas fa-stream"></i>
                                <span>Timeline</span>
                            </button>
                            <button @click="viewMode = 'table'" 
                                    :class="viewMode === 'table' ? 'bg-white dark:bg-gray-600 text-primary dark:text-white shadow-sm' : 'text-gray-600 dark:text-gray-400'"
                                    class="px-4 py-2 rounded-md text-sm font-medium transition-all flex items-center gap-2">
                                <i class="fas fa-table"></i>
                                <span>Tabel</span>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Timeline View -->
                <div x-show="viewMode === 'timeline'" class="p-5 lg:p-6">
                    <?php if(mysqli_num_rows($riwayat) > 0): ?>
                    <div class="relative">
                        <?php 
                        mysqli_data_seek($riwayat, 0);
                        $no = 1;
                        $total_rows = mysqli_num_rows($riwayat);
                        while($row = mysqli_fetch_assoc($riwayat)): 
                            $row_style = $kondisi_styles[$row['kondisi']] ?? $kondisi_styles['Belum Dicek'];
                            $is_last = ($no === $total_rows);
                        ?>
                        <div class="stagger-item relative flex gap-4 <?= !$is_last ? 'pb-8' : '' ?>">
                            <!-- Connector Line -->
                            <?php if(!$is_last): ?>
                            <div class="timeline-connector"></div>
                            <?php endif; ?>
                            
                            <!-- Timeline Dot -->
                            <div class="relative flex-shrink-0">
                                <div class="timeline-dot w-12 h-12 rounded-full bg-gradient-to-br <?= $row_style['gradient'] ?> flex items-center justify-center text-white shadow-lg">
                                    <i class="fas <?= $row_style['icon'] ?>"></i>
                                </div>
                            </div>
                            
                            <!-- Content Card -->
                            <div class="flex-1 bg-gradient-to-br from-gray-50 to-white dark:from-gray-700/50 dark:to-gray-800 rounded-xl p-5 border border-gray-200 dark:border-gray-600 hover:shadow-lg transition-all">
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold text-white bg-gradient-to-r <?= $row_style['gradient'] ?>">
                                                <i class="fas <?= $row_style['icon'] ?> mr-1.5"></i>
                                                <?= $row['kondisi'] ?>
                                            </span>
                                            <?php if($no === 1): ?>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                                <i class="fas fa-star mr-1"></i> TERKINI
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span class="font-semibold"><?= formatTanggal($row['tanggal_cek']) ?></span>
                                            <span class="text-gray-400">•</span>
                                            <i class="fas fa-clock"></i>
                                            <span><?= date('H:i', strtotime($row['created_at'])) ?> WIB</span>
                                        </p>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <div class="w-7 h-7 rounded-full bg-primary/10 flex items-center justify-center">
                                            <i class="fas fa-user text-primary text-xs"></i>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($row['petugas']) ?></span>
                                    </div>
                                </div>
                                
                                <?php if($row['keterangan']): ?>
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1 flex items-center gap-1">
                                        <i class="fas fa-note-sticky"></i>
                                        <span class="font-semibold">Keterangan:</span>
                                    </p>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 italic pl-5">
                                        "<?= nl2br(htmlspecialchars($row['keterangan'])) ?>"
                                    </p>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Detail JSON (jika ada) -->
                                <?php if(!empty($row['detail_kondisi'])): 
                                    $detail = json_decode($row['detail_kondisi'], true);
                                    if($detail && is_array($detail)):
                                ?>
                                <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-600">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
                                        <i class="fas fa-chart-pie"></i>
                                        <span class="font-semibold">Detail Kondisi:</span>
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 pl-5">
                                        <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-2 text-center">
                                            <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Baik</p>
                                            <p class="text-sm font-bold text-emerald-700 dark:text-emerald-300"><?= $detail['baik'] ?? 0 ?></p>
                                        </div>
                                        <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-2 text-center">
                                            <p class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold">Ringan</p>
                                            <p class="text-sm font-bold text-amber-700 dark:text-amber-300"><?= $detail['rusak_ringan'] ?? 0 ?></p>
                                        </div>
                                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-2 text-center">
                                            <p class="text-[10px] text-red-600 dark:text-red-400 font-semibold">Berat</p>
                                            <p class="text-sm font-bold text-red-700 dark:text-red-300"><?= $detail['rusak_berat'] ?? 0 ?></p>
                                        </div>
                                        <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-2 text-center">
                                            <p class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold">Perbaikan</p>
                                            <p class="text-sm font-bold text-blue-700 dark:text-blue-300"><?= $detail['dalam_perbaikan'] ?? 0 ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; endif; ?>
                            </div>
                        </div>
                        <?php $no++; endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-gray-100 dark:bg-gray-700 rounded-full mb-4">
                            <i class="fas fa-inbox text-3xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada riwayat kondisi</p>
                        <p class="text-sm text-gray-400 mt-1">Riwayat akan muncul setelah kondisi aset diupdate</p>
                        <a href="kondisi_aset.php" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all">
                            <i class="fas fa-arrow-left"></i>
                            Kembali ke Kondisi Aset
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Table View -->
                <div x-show="viewMode === 'table'" class="overflow-x-auto">
                    <?php if(mysqli_num_rows($riwayat) > 0): ?>
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">No</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Tanggal Cek</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Kondisi</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Keterangan</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Petugas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php 
                            mysqli_data_seek($riwayat, 0);
                            $no = 1;
                            while($row = mysqli_fetch_assoc($riwayat)): 
                                $badgeClasses = [
                                    'Baik' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    'Rusak Ringan' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300',
                                    'Rusak Berat' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300',
                                    'Dalam Perbaikan' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300',
                                ];
                                $badgeClass = $badgeClasses[$row['kondisi']] ?? 'bg-gray-100 text-gray-700 border-gray-200';
                                
                                $iconClasses = [
                                    'Baik' => 'fa-check-circle text-emerald-500',
                                    'Rusak Ringan' => 'fa-exclamation-triangle text-amber-500',
                                    'Rusak Berat' => 'fa-times-circle text-red-500',
                                    'Dalam Perbaikan' => 'fa-wrench text-blue-500',
                                ];
                                $iconClass = $iconClasses[$row['kondisi']] ?? 'fa-question-circle text-gray-500';
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-4 text-sm font-medium text-gray-600 dark:text-gray-300"><?= $no++ ?></td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">
                                        <div class="flex items-center gap-1.5">
                                            <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                            <span class="font-medium"><?= formatTanggal($row['tanggal_cek']) ?></span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?= date('H:i', strtotime($row['created_at'])) ?> WIB
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border <?= $badgeClass ?>">
                                        <i class="fas <?= $iconClass ?> text-xs"></i>
                                        <?= $row['kondisi'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-300 max-w-md">
                                        <?= htmlspecialchars($row['keterangan'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                                            <i class="fas fa-user text-primary text-xs"></i>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($row['petugas']) ?></span>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                
            </div>
            
        </div>
    </main>
</div>

<script>
// Animate timeline items
document.addEventListener('DOMContentLoaded', () => {
    const items = document.querySelectorAll('.stagger-item');
    items.forEach((item, idx) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';
        setTimeout(() => {
            item.style.transition = 'all 0.5s ease-out';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, idx * 100);
    });
});
</script>

</body>
</html>