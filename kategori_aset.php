<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ 7 KATEGORI FIXED
$daftar_kategori = [
    [
        'nama' => 'Buku & Bahan Ajar',
        'kode' => 'BUKU',
        'icon' => 'fa-book',
        'gradient' => 'from-blue-500 to-indigo-600',
        'bg' => 'bg-blue-50 dark:bg-blue-900/20',
        'text' => 'text-blue-700 dark:text-blue-300',
        'border' => 'border-blue-200 dark:border-blue-800',
        'desc' => 'Buku pelajaran, modul, LKS, dan bahan ajar lainnya'
    ],
    [
        'nama' => 'Alat Tulis Kantor (ATK)',
        'kode' => 'ATK',
        'icon' => 'fa-pen-fancy',
        'gradient' => 'from-purple-500 to-purple-600',
        'bg' => 'bg-purple-50 dark:bg-purple-900/20',
        'text' => 'text-purple-700 dark:text-purple-300',
        'border' => 'border-purple-200 dark:border-purple-800',
        'desc' => 'Pulpen, pensil, kertas, map, dan alat tulis lainnya'
    ],
    [
        'nama' => 'Perlengkapan Komputer & Printer',
        'kode' => 'KOM',
        'icon' => 'fa-laptop',
        'gradient' => 'from-cyan-500 to-blue-600',
        'bg' => 'bg-cyan-50 dark:bg-cyan-900/20',
        'text' => 'text-cyan-700 dark:text-cyan-300',
        'border' => 'border-cyan-200 dark:border-cyan-800',
        'desc' => 'Komputer, laptop, printer, scanner, dan periferal'
    ],
    [
        'nama' => 'Perlengkapan Kebersihan',
        'kode' => 'KBR',
        'icon' => 'fa-broom',
        'gradient' => 'from-emerald-500 to-green-600',
        'bg' => 'bg-emerald-50 dark:bg-emerald-900/20',
        'text' => 'text-emerald-700 dark:text-emerald-300',
        'border' => 'border-emerald-200 dark:border-emerald-800',
        'desc' => 'Sapu, pel, tempat sampah, dan alat kebersihan'
    ],
    [
        'nama' => 'Perlengkapan Kesehatan',
        'kode' => 'KES',
        'icon' => 'fa-heart-pulse',
        'gradient' => 'from-red-500 to-pink-600',
        'bg' => 'bg-red-50 dark:bg-red-900/20',
        'text' => 'text-red-700 dark:text-red-300',
        'border' => 'border-red-200 dark:border-red-800',
        'desc' => 'P3K, timbangan, tensimeter, dan alat kesehatan UKS'
    ],
    [
        'nama' => 'Peralatan Olahraga',
        'kode' => 'OHR',
        'icon' => 'fa-basketball',
        'gradient' => 'from-orange-500 to-amber-600',
        'bg' => 'bg-orange-50 dark:bg-orange-900/20',
        'text' => 'text-orange-700 dark:text-orange-300',
        'border' => 'border-orange-200 dark:border-orange-800',
        'desc' => 'Bola, raket, matras, dan peralatan olahraga'
    ],
    [
        'nama' => 'Peralatan dan Sarana Pendukung Sekolah',
        'kode' => 'SAR',
        'icon' => 'fa-school',
        'gradient' => 'from-primary to-primary-light',
        'bg' => 'bg-primary/10',
        'text' => 'text-primary dark:text-primary-light',
        'border' => 'border-primary/20 dark:border-primary/30',
        'desc' => 'Meja, kursi, papan tulis, dan sarana pendukung'
    ],
];

// ✅ HITUNG TOTAL ASET & NILAI PER KATEGORI
foreach($daftar_kategori as &$kat) {
    $nama_esc = mysqli_real_escape_string($conn, $kat['nama']);
    
    $stat = mysqli_query($conn, "SELECT 
        COUNT(*) as total_aset,
        COALESCE(SUM(jumlah), 0) as total_jumlah,
        COALESCE(SUM(total), 0) as total_nilai
        FROM inventaris 
        WHERE kategori_id = '$nama_esc'")->fetch_assoc();
    
    $kat['total_aset'] = (int)$stat['total_aset'];
    $kat['total_jumlah'] = (int)$stat['total_jumlah'];
    $kat['total_nilai'] = (float)$stat['total_nilai'];
}
unset($kat);

// ✅ TOTAL KESELURUHAN
$total_aset_all = array_sum(array_column($daftar_kategori, 'total_aset'));
$total_nilai_all = array_sum(array_column($daftar_kategori, 'total_nilai'));

// ✅ SEARCH KATEGORI
$search = isset($_GET['cari']) ? mysqli_real_escape_string($conn, $_GET['cari']) : '';
if($search) {
    $daftar_kategori = array_filter($daftar_kategori, function($k) use ($search) {
        return stripos($k['nama'], $search) !== false || stripos($k['kode'], $search) !== false;
    });
}

// ✅ VIEW DETAIL ASET PER KATEGORI
$view_nama = isset($_GET['view']) ? $_GET['view'] : '';
$view_kategori = null;
$view_assets = null;
$view_summary = null;
$view_ruangan_top = null;

if($view_nama) {
    foreach($daftar_kategori as $k) {
        if($k['nama'] === $view_nama) {
            $view_kategori = $k;
            break;
        }
    }
    
    if($view_kategori) {
        $nama_esc = mysqli_real_escape_string($conn, $view_kategori['nama']);
        
        // Query utama: ambil semua aset + kondisi terakhir + info ruangan
        $view_assets = mysqli_query($conn, "
            SELECT i.*, 
                   r.nama_ruangan, r.kode_ruangan,
                   ks.kondisi AS kondisi_terakhir,
                   ks.tanggal_cek AS tgl_cek_kondisi
            FROM inventaris i 
            LEFT JOIN ruangan r ON i.ruangan_id = r.id 
            LEFT JOIN (
                SELECT k1.inventaris_id, k1.kondisi, k1.tanggal_cek
                FROM kondisi_aset k1
                INNER JOIN (
                    SELECT inventaris_id, MAX(created_at) AS max_created_at
                    FROM kondisi_aset
                    GROUP BY inventaris_id
                ) k2 ON k1.inventaris_id = k2.inventaris_id 
                    AND k1.created_at = k2.max_created_at
            ) ks ON ks.inventaris_id = i.id
            WHERE i.kategori_id = '$nama_esc' 
            ORDER BY i.created_at DESC
        ");
        
        // Summary stats untuk detail view
        $view_summary = mysqli_query($conn, "
            SELECT 
                COUNT(*) as total_jenis,
                COALESCE(SUM(jumlah), 0) as total_unit,
                COALESCE(SUM(total), 0) as total_nilai,
                COALESCE(AVG(harga_satuan), 0) as rata_harga,
                COALESCE(MAX(total), 0) as nilai_tertinggi,
                COALESCE(MIN(CASE WHEN total > 0 THEN total END), 0) as nilai_terendah,
                COUNT(DISTINCT ruangan_id) as total_ruangan
            FROM inventaris 
            WHERE kategori_id = '$nama_esc'
        ")->fetch_assoc();
        
        // Top 3 ruangan yang paling banyak menyimpan aset kategori ini
        $view_ruangan_top = mysqli_query($conn, "
            SELECT r.nama_ruangan, r.kode_ruangan, COUNT(*) as jumlah_aset, 
                   COALESCE(SUM(i.jumlah), 0) as total_unit
            FROM inventaris i
            LEFT JOIN ruangan r ON i.ruangan_id = r.id
            WHERE i.kategori_id = '$nama_esc'
            GROUP BY i.ruangan_id, r.nama_ruangan, r.kode_ruangan
            ORDER BY jumlah_aset DESC
            LIMIT 3
        ");
    }
}

function formatRupiahLocal($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function getKondisiBadge($kondisi) {
    if (!$kondisi) return ['class' => 'bg-gray-100 text-gray-500 border-gray-200 dark:bg-gray-700 dark:text-gray-400', 'icon' => 'fa-question-circle', 'label' => 'Belum Dicek'];
    
    $map = [
        'Baik' => ['class' => 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300', 'icon' => 'fa-check-circle'],
        'Rusak Ringan' => ['class' => 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300', 'icon' => 'fa-exclamation-triangle'],
        'Rusak Berat' => ['class' => 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/30 dark:text-red-300', 'icon' => 'fa-times-circle'],
        'Dalam Perbaikan' => ['class' => 'bg-blue-100 text-blue-700 border-blue-200 dark:bg-blue-900/30 dark:text-blue-300', 'icon' => 'fa-wrench'],
    ];
    
    $data = $map[$kondisi] ?? ['class' => 'bg-gray-100 text-gray-500 border-gray-200', 'icon' => 'fa-question-circle'];
    $data['label'] = $kondisi;
    return $data;
}

function getSumberBadge($sumber) {
    $map = [
        'Pemerintah' => 'bg-primary/10 text-primary border-primary/20',
        'Sekolah' => 'bg-amber-100 text-amber-700 border-amber-200',
        'BOS' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
        'DAK' => 'bg-blue-100 text-blue-700 border-blue-200',
        'APBD' => 'bg-purple-100 text-purple-700 border-purple-200',
    ];
    return $map[$sumber] ?? 'bg-gray-100 text-gray-500 border-gray-200';
}
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Aset - Inventaris SDN Curug 01</title>
    
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
                        'slide-up': 'slideUp 0.4s ease-out',
                        'slide-in-left': 'slideInLeft 0.3s ease-out'
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(30px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
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
        
        .category-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .category-card:hover {
            transform: translateY(-6px);
        }
        .category-card:hover .card-icon {
            transform: scale(1.1) rotate(-5deg);
        }
        .card-icon {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .stagger-item { animation: slideUp 0.5s ease-out backwards; }
        .stagger-item:nth-child(1) { animation-delay: 0.05s; }
        .stagger-item:nth-child(2) { animation-delay: 0.1s; }
        .stagger-item:nth-child(3) { animation-delay: 0.15s; }
        .stagger-item:nth-child(4) { animation-delay: 0.2s; }
        .stagger-item:nth-child(5) { animation-delay: 0.25s; }
        .stagger-item:nth-child(6) { animation-delay: 0.3s; }
        .stagger-item:nth-child(7) { animation-delay: 0.35s; }
        
        .grid-pattern {
            background-image: 
                linear-gradient(rgba(26, 54, 93, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(26, 54, 93, 0.03) 1px, transparent 1px);
            background-size: 20px 20px;
        }
        
        html { scroll-behavior: smooth; }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="kategoriApp()">
    
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
                        <span class="text-primary font-semibold">Kategori Aset</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-tags"></i>
                        <span>Kategori Aset</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Info Banner -->
            <div class="bg-gradient-to-r from-primary via-primary-light to-primary rounded-2xl shadow-xl p-5 lg:p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-48 h-48 bg-white/5 rounded-full -translate-y-24 translate-x-24"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/5 rounded-full translate-y-16 -translate-x-16"></div>
                
                <div class="relative flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                            <i class="fas fa-layer-group text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold">7 Kategori Aset Sekolah</h3>
                            <p class="text-sm text-white/80">Klasifikasi aset berdasarkan Permendagri 108</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="text-center px-4 py-2 bg-white/15 backdrop-blur rounded-lg">
                            <p class="text-2xl font-bold"><?= number_format($total_aset_all) ?></p>
                            <p class="text-[10px] uppercase tracking-wider text-white/80">Total Aset</p>
                        </div>
                        <div class="text-center px-4 py-2 bg-white/15 backdrop-blur rounded-lg">
                            <p class="text-sm font-bold"><?= formatRupiahLocal($total_nilai_all) ?></p>
                            <p class="text-[10px] uppercase tracking-wider text-white/80">Total Nilai</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               name="cari" 
                               placeholder="Cari kategori..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full pl-11 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                    </div>
                    <button type="submit" 
                            class="px-6 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                        <i class="fas fa-filter"></i>
                        <span>Cari</span>
                    </button>
                    <?php if($search): ?>
                    <a href="kategori_aset.php" 
                       class="px-6 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                        <i class="fas fa-times"></i>
                        <span>Reset</span>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Categories Grid -->
            <?php if(count($daftar_kategori) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-5">
                <?php 
                foreach($daftar_kategori as $idx => $kat): 
                ?>
                <div class="stagger-item category-card group bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl overflow-hidden border border-gray-100 dark:border-gray-700">
                    
                    <!-- Card Header with Gradient -->
                    <div class="relative h-28 bg-gradient-to-br <?= $kat['gradient'] ?> overflow-hidden">
                        <div class="absolute inset-0 grid-pattern opacity-30"></div>
                        <div class="absolute -right-4 -top-4 w-32 h-32 bg-white/10 rounded-full"></div>
                        <div class="absolute -left-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full"></div>
                        
                        <div class="relative p-5 h-full flex flex-col justify-between">
                            <div class="flex items-start justify-between">
                                <div class="card-icon w-12 h-12 rounded-xl bg-white/20 backdrop-blur flex items-center justify-center text-white shadow-lg">
                                    <i class="fas <?= $kat['icon'] ?> text-xl"></i>
                                </div>
                                <span class="text-[10px] px-2 py-1 bg-white/20 backdrop-blur text-white rounded-full font-mono font-bold">
                                    <?= $kat['kode'] ?>
                                </span>
                            </div>
                            <div>
                                <h3 class="text-white font-bold text-sm line-clamp-2 drop-shadow-md">
                                    <?= $kat['nama'] ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Card Body -->
                    <div class="p-4">
                        <!-- Stats -->
                        <div class="grid grid-cols-2 gap-2 mb-3">
                            <div class="text-center p-2 <?= $kat['bg'] ?> rounded-lg">
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Jenis Aset</p>
                                <p class="text-lg font-bold <?= $kat['text'] ?>"><?= number_format($kat['total_aset']) ?></p>
                            </div>
                            <div class="text-center p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Total Nilai</p>
                                <p class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate">
                                    <?= formatRupiahLocal($kat['total_nilai']) ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Description -->
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2 mb-3 min-h-[2.5rem]">
                            <?= $kat['desc'] ?>
                        </p>
                        
                        <!-- Action Button -->
                        <a href="?view=<?= urlencode($kat['nama']) ?>#detail-aset" 
                           class="w-full py-2 px-3 bg-primary/10 hover:bg-primary text-primary hover:text-white rounded-lg transition-all text-xs font-semibold flex items-center justify-center gap-1.5">
                            <i class="fas fa-eye text-[10px]"></i>
                            <span>Lihat <?= $kat['total_aset'] ?> Aset</span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <!-- Empty State -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-12 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div class="w-24 h-24 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                        <i class="fas fa-search text-4xl text-gray-300 dark:text-gray-600"></i>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">Kategori Tidak Ditemukan</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Coba kata kunci pencarian lain</p>
                    </div>
                    <a href="kategori_aset.php" class="mt-2 px-6 py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md transition-all text-sm font-medium">
                        Reset Pencarian
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ✅ VIEW DETAIL ASSETS - ENHANCED VERSION -->
            <?php if($view_kategori && $view_assets && $view_summary): ?>
            <div id="detail-aset" class="bg-white dark:bg-gray-800 rounded-xl shadow-lg border-2 border-primary/20 dark:border-primary/30 overflow-hidden animate-slide-up scroll-mt-24">
                
                <!-- Header Detail -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 via-transparent to-transparent">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br <?= $view_kategori['gradient'] ?> flex items-center justify-center text-white shadow-xl">
                                <i class="fas <?= $view_kategori['icon'] ?> text-2xl"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h3 class="text-xl font-bold text-gray-800 dark:text-white">
                                        <?= $view_kategori['nama'] ?>
                                    </h3>
                                    <span class="text-xs px-2 py-0.5 bg-primary/10 text-primary rounded font-mono font-bold">
                                        <?= $view_kategori['kode'] ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    <?= $view_kategori['desc'] ?>
                                </p>
                            </div>
                        </div>
                        <a href="kategori_aset.php" 
                           class="self-start lg:self-center px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center gap-2 text-sm font-medium">
                            <i class="fas fa-times"></i>
                            <span>Tutup</span>
                        </a>
                    </div>
                </div>
                
                <!-- Summary Cards -->
                <div class="p-5 lg:p-6 bg-gradient-to-br from-gray-50 to-white dark:from-gray-900/50 dark:to-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 lg:gap-4">
                        <!-- Total Jenis -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                    <i class="fas fa-boxes-stacked text-blue-600 dark:text-blue-400 text-sm"></i>
                                </div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Jenis Barang</p>
                            </div>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($view_summary['total_jenis']) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Varian berbeda</p>
                        </div>
                        
                        <!-- Total Unit -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                                    <i class="fas fa-cubes text-emerald-600 dark:text-emerald-400 text-sm"></i>
                                </div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Total Unit</p>
                            </div>
                            <p class="text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($view_summary['total_unit']) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Unit fisik</p>
                        </div>
                        
                        <!-- Total Nilai -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                    <i class="fas fa-coins text-amber-600 dark:text-amber-400 text-sm"></i>
                                </div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Total Nilai</p>
                            </div>
                            <p class="text-xl font-bold text-gray-800 dark:text-white truncate"><?= formatRupiahLocal($view_summary['total_nilai']) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Nilai keseluruhan</p>
                        </div>
                        
                        <!-- Rata-rata Harga -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                    <i class="fas fa-chart-line text-purple-600 dark:text-purple-400 text-sm"></i>
                                </div>
                                <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold">Rata-rata</p>
                            </div>
                            <p class="text-xl font-bold text-gray-800 dark:text-white truncate"><?= formatRupiahLocal($view_summary['rata_harga']) ?></p>
                            <p class="text-[10px] text-gray-400 mt-1">Per unit barang</p>
                        </div>
                    </div>
                    
                    <!-- Additional Info Row -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                        <!-- Top Ruangan -->
                        <?php if($view_ruangan_top && mysqli_num_rows($view_ruangan_top) > 0): ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 mb-3 flex items-center gap-2">
                                <i class="fas fa-trophy text-amber-500"></i>
                                Ruangan Terbanyak
                            </p>
                            <div class="space-y-2">
                                <?php 
                                $rank = 1;
                                $rank_colors = ['text-amber-500', 'text-gray-400', 'text-orange-400'];
                                $rank_icons = ['fa-crown', 'fa-medal', 'fa-medal'];
                                while($r = mysqli_fetch_assoc($view_ruangan_top)): 
                                ?>
                                <div class="flex items-center justify-between p-2 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                    <div class="flex items-center gap-2 min-w-0 flex-1">
                                        <i class="fas <?= $rank_icons[$rank-1] ?? 'fa-star' ?> <?= $rank_colors[$rank-1] ?? 'text-gray-400' ?> text-sm"></i>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-xs font-semibold text-gray-800 dark:text-white truncate">
                                                <?= htmlspecialchars($r['nama_ruangan'] ?? 'Tidak diketahui') ?>
                                            </p>
                                            <p class="text-[10px] text-gray-500 font-mono"><?= htmlspecialchars($r['kode_ruangan'] ?? '-') ?></p>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0 ml-2">
                                        <p class="text-xs font-bold text-primary dark:text-primary-light"><?= $r['jumlah_aset'] ?> jenis</p>
                                        <p class="text-[10px] text-gray-400"><?= $r['total_unit'] ?> unit</p>
                                    </div>
                                </div>
                                <?php $rank++; endwhile; ?>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 mb-3 flex items-center gap-2">
                                <i class="fas fa-trophy text-amber-500"></i>
                                Ruangan Terbanyak
                            </p>
                            <p class="text-xs text-gray-400 italic text-center py-4">Belum ada data</p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Quick Stats -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-100 dark:border-gray-700 shadow-sm">
                            <p class="text-xs font-bold text-gray-700 dark:text-gray-200 mb-3 flex items-center gap-2">
                                <i class="fas fa-chart-pie text-primary"></i>
                                Statistik Nilai
                            </p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                                    <span class="text-xs text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
                                        <i class="fas fa-arrow-up"></i> Nilai Tertinggi
                                    </span>
                                    <span class="text-xs font-bold text-emerald-700 dark:text-emerald-300">
                                        <?= formatRupiahLocal($view_summary['nilai_tertinggi']) ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                    <span class="text-xs text-blue-700 dark:text-blue-300 flex items-center gap-2">
                                        <i class="fas fa-arrow-down"></i> Nilai Terendah
                                    </span>
                                    <span class="text-xs font-bold text-blue-700 dark:text-blue-300">
                                        <?= $view_summary['nilai_terendah'] > 0 ? formatRupiahLocal($view_summary['nilai_terendah']) : '-' ?>
                                    </span>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-purple-50 dark:bg-purple-900/20 rounded-lg">
                                    <span class="text-xs text-purple-700 dark:text-purple-300 flex items-center gap-2">
                                        <i class="fas fa-door-open"></i> Tersebar di
                                    </span>
                                    <span class="text-xs font-bold text-purple-700 dark:text-purple-300">
                                        <?= $view_summary['total_ruangan'] ?> ruangan
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Table Header with Search -->
                <div class="p-4 lg:p-5 border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-list text-primary"></i>
                            <h4 class="font-bold text-gray-800 dark:text-white">Daftar Barang</h4>
                            <span class="text-xs px-2 py-0.5 bg-primary/10 text-primary rounded-full font-semibold">
                                <?= mysqli_num_rows($view_assets) ?> item
                            </span>
                        </div>
                        <div class="relative w-full sm:w-72">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                            <input type="text" 
                                   id="search-detail"
                                   placeholder="Cari dalam daftar..."
                                   onkeyup="filterDetailTable()"
                                   class="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-xs">
                        </div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full" id="detail-table">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Nama Barang</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Spesifikasi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Lokasi</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Jumlah</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Kondisi</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Sumber</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider">Harga</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if(mysqli_num_rows($view_assets) > 0): 
                                $n = 1;
                                $subtotal = 0;
                                while($it = mysqli_fetch_assoc($view_assets)): 
                                    $subtotal += $it['total'];
                                    $kondisi = getKondisiBadge($it['kondisi_terakhir']);
                                    $sumber = getSumberBadge($it['sumber_pengadaan']);
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors detail-row">
                                <td class="px-4 py-3 text-sm font-medium text-gray-600 dark:text-gray-300"><?= $n++ ?></td>
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-br <?= $view_kategori['gradient'] ?> bg-opacity-20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas <?= $view_kategori['icon'] ?> text-white text-xs"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-800 dark:text-white line-clamp-1">
                                                <?= htmlspecialchars($it['nama_barang_108']) ?>
                                            </div>
                                            <?php if($it['kode_barang_108']): ?>
                                            <div class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($it['kode_barang_108']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-gray-600 dark:text-gray-300 max-w-xs" 
                                         title="<?= htmlspecialchars($it['spesifikasi_nama_barang']) ?>">
                                        <?= htmlspecialchars(substr($it['spesifikasi_nama_barang'] ?? '-', 0, 60)) ?><?= strlen($it['spesifikasi_nama_barang'] ?? '') > 60 ? '...' : '' ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= htmlspecialchars($it['nama_ruangan'] ?? '-') ?></div>
                                        <?php if($it['kode_ruangan']): ?>
                                        <div class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($it['kode_ruangan']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-200">
                                        <?= $it['jumlah'] ?>
                                    </span>
                                    <span class="text-[10px] text-gray-500 block"><?= $it['satuan'] ?></span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-semibold border <?= $kondisi['class'] ?>">
                                        <i class="fas <?= $kondisi['icon'] ?> text-[9px]"></i>
                                        <?= $kondisi['label'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-semibold border <?= $sumber ?>">
                                        <?= htmlspecialchars($it['sumber_pengadaan'] ?: '-') ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="text-xs text-gray-700 dark:text-gray-200">
                                        <?= formatRupiahLocal($it['harga_satuan']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-sm font-bold text-primary dark:text-primary-light">
                                        <?= formatRupiahLocal($it['total']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <!-- Subtotal Row -->
                            <tr class="bg-gradient-to-r from-primary/10 to-transparent font-bold border-t-2 border-primary/30">
                                <td colspan="8" class="px-4 py-4 text-right text-sm text-gray-800 dark:text-gray-200">
                                    <div class="flex items-center justify-end gap-2">
                                        <i class="fas fa-calculator text-primary"></i>
                                        <span>SUBTOTAL <?= strtoupper($view_kategori['nama']) ?>:</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <span class="text-base font-bold text-primary dark:text-primary-light">
                                        <?= formatRupiahLocal($subtotal) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada aset dalam kategori ini</p>
                                        <a href="tambah.php" class="mt-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all inline-flex items-center gap-2">
                                            <i class="fas fa-plus"></i>
                                            Tambah Aset Pertama
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer -->
                <div class="p-4 lg:p-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-4">
                            <span><i class="fas fa-table-cells mr-1"></i> <?= mysqli_num_rows($view_assets) ?> baris</span>
                            <span><i class="fas fa-door-open mr-1"></i> <?= $view_summary['total_ruangan'] ?> ruangan</span>
                            <span class="font-bold text-primary"><i class="fas fa-coins mr-1"></i> <?= formatRupiahLocal($subtotal ?? 0) ?></span>
                        </div>
                        <a href="kategori_aset.php" 
                           class="px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg transition-all inline-flex items-center gap-2 text-xs font-semibold">
                            <i class="fas fa-arrow-up"></i>
                            Kembali ke Kategori
                        </a>
                    </div>
                </div>
            </div>
            
            <script>
                // Auto-scroll to detail section when loaded
                <?php if($view_kategori): ?>
                setTimeout(() => {
                    const detailSection = document.getElementById('detail-aset');
                    if (detailSection) {
                        detailSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 300);
                <?php endif; ?>
                
                // Local search filter for detail table
                function filterDetailTable() {
                    const searchInput = document.getElementById('search-detail');
                    const filter = searchInput.value.toLowerCase();
                    const rows = document.querySelectorAll('#detail-table .detail-row');
                    let visibleCount = 0;
                    
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        if (text.includes(filter)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                }
            </script>
            <?php endif; ?>
            
        </div>
    </main>
</div>

<script>
function kategoriApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        },
        
        confirmLogout() {s
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
</script>

</body>
</html>