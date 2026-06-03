<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Statistik
$total_aset = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];
$total_pemerintah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Pemerintah'")->fetch_assoc()['total'];
$total_sekolah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Sekolah'")->fetch_assoc()['total'];
$total_nilai = mysqli_query($conn, "SELECT SUM(total) as total FROM inventaris")->fetch_assoc()['total'];

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "";
if($search) {
    $where = "WHERE spesifikasi_nama_barang LIKE '%$search%' OR nama_barang_108 LIKE '%$search%'";
}

$result = mysqli_query($conn, "SELECT * FROM inventaris $where ORDER BY created_at DESC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventaris Sekolah</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            DEFAULT: '#1a365d',
                            dark: '#0f2744',
                            light: '#2c5282'
                        },
                        accent: {
                            gold: '#d69e2e',
                            green: '#38a169',
                            red: '#e53e3e'
                        }
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
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': { transform: 'translateY(20px)', opacity: '0' },
                            '100%': { transform: 'translateY(0)', opacity: '1' }
                        },
                        slideInLeft: {
                            '0%': { transform: 'translateX(-100%)' },
                            '100%': { transform: 'translateX(0)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Custom scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #1a365d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #0f2744; }
        .dark ::-webkit-scrollbar-track { background: #2d3748; }
        
        /* Smooth transitions */
        * { transition: background-color 0.3s ease, color 0.2s ease, border-color 0.3s ease; }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
        }
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        
        /* Stagger animation for cards */
        .stagger-item { animation: slideUp 0.5s ease-out backwards; }
        .stagger-item:nth-child(1) { animation-delay: 0.1s; }
        .stagger-item:nth-child(2) { animation-delay: 0.2s; }
        .stagger-item:nth-child(3) { animation-delay: 0.3s; }
        .stagger-item:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="dashboardApp()">
    
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
         style="display: none;">
    </div>
    
    <!-- Sidebar -->
    <aside 
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed lg:translate-x-0 lg:static inset-y-0 left-0 z-50 w-64 bg-primary text-white flex flex-col shadow-2xl transition-transform duration-300 ease-in-out">
        
        <!-- Brand -->
        <div class="p-6 text-center border-b border-white/10">
            <img src="assets/img/logo.png" 
                 onerror="this.src='https://ui-avatars.com/api/?name=SDN&background=ffffff&color=1a365d&size=120'"
                 class="w-24 h-24 rounded-full mx-auto mb-3 object-cover border-4 border-white/20 hover:scale-110 transition-transform duration-300"
                 alt="Logo">
            <h4 class="text-lg font-semibold text-white">Inventaris Sekolah</h4>
            <p class="text-xs text-gray-300 mt-1">SDN Curug 01</p>
        </div>
        
        <!-- Menu -->
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
        
        <!-- Logout -->
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
                <!-- Mobile menu button -->
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
                <!-- Dark Mode Toggle -->
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
                        title="Toggle Dark Mode">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                
                <!-- Notifications -->
                <button @click="showNotifications()" 
                        class="relative p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas fa-bell text-gray-600 dark:text-gray-300"></i>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                </button>
                
                <!-- Add Asset Button -->
                <a href="tambah.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle"></i>
                    <span class="text-sm font-medium">Tambah Aset</span>
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <?php 
                $stats = [
                    ['Total Aset', $total_aset, 'fa-boxes-stacked', 'from-blue-500 to-blue-600', 'Total keseluruhan aset'],
                    ['Aset Pemerintah', $total_pemerintah, 'fa-landmark', 'from-primary to-primary-dark', 'Pengadaan dari pemerintah'],
                    ['Aset Sekolah', $total_sekolah, 'fa-school', 'from-amber-500 to-amber-600', 'Pengadaan dari sekolah'],
                    ['Total Nilai', formatRupiah($total_nilai), 'fa-coins', 'from-emerald-500 to-emerald-600', 'Nilai total inventaris'],
                ];
                foreach($stats as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-5 lg:p-6">
                        <div class="flex items-start justify-between mb-3">
                            <div class="p-3 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-lg"></i>
                            </div>
                            <span class="text-xs text-gray-400 dark:text-gray-500 font-medium">#<?= $idx + 1 ?></span>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-2xl lg:text-3xl font-bold text-gray-800 dark:text-white truncate" 
                            x-data="{ count: 0, target: <?= is_numeric($stat[1]) ? $stat[1] : 0 ?> }"
                            x-init="$nextTick(() => { animateCount() })"
                            x-text="<?= is_numeric($stat[1]) ? 'target' : "'" . $stat[1] . "'" ?>">
                            <?= $stat[1] ?>
                        </h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-2"><?= $stat[4] ?></p>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Data Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden"
                 x-data="tableApp()">
                
                <!-- Card Header -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-list text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Data Inventaris</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Menampilkan <span class="font-semibold text-primary"><?= $total_records ?></span> data
                                </p>
                            </div>
                        </div>
                        
                        <!-- Search -->
                        <form method="GET" class="flex gap-2 w-full lg:w-auto" @submit="handleSearch($event)">
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
                </div>
                
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
                                        <p class="text-sm text-gray-400">Coba kata kunci pencarian lain</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $badgeClass = $row['sumber_pengadaan'] == 'Pemerintah' 
                                        ? 'bg-primary/10 text-primary border-primary/20' 
                                        : ($row['sumber_pengadaan'] == 'Sekolah' 
                                            ? 'bg-amber-100 text-amber-700 border-amber-200' 
                                            : 'bg-emerald-100 text-emerald-700 border-emerald-200');
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
                                        <a href="edit.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-amber-100 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <button @click="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_barang_108'])) ?>')"
                                                class="p-2 bg-red-100 hover:bg-red-500 text-red-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                                title="Hapus">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                        <a href="detail.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Detail">
                                            <i class="fas fa-eye text-sm"></i>
                                        </a>
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
                        </p>
                        <nav class="flex items-center gap-1">
                            <!-- Previous -->
                            <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if($start_page > 1): ?>
                                <a href="?page=1&search=<?= urlencode($search) ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">1</a>
                                <?php if($start_page > 2): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>" 
                                   class="px-3 py-2 text-sm rounded-lg transition-all <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-primary hover:text-white hover:border-primary' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($end_page < $total_pages): ?>
                                <?php if($end_page < $total_pages - 1): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all"><?= $total_pages ?></a>
                            <?php endif; ?>
                            
                            <!-- Next -->
                            <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>" 
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

<!-- Toast Notification Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
// ============================================
// Modern JavaScript - Dashboard Application
// ============================================

// Alpine.js - Main Dashboard Component
function dashboardApp() {
    return {
        init() {
            // Check for URL parameters (success/error messages)
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
            this.showToast(
                this.darkMode ? 'Mode gelap diaktifkan' : 'Mode terang diaktifkan', 
                'info'
            );
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
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
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
            const colors = {
                success: 'bg-emerald-500',
                error: 'bg-red-500',
                info: 'bg-blue-500',
                warning: 'bg-amber-500'
            };
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-times-circle',
                info: 'fa-info-circle',
                warning: 'fa-exclamation-triangle'
            };
            
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

// Alpine.js - Table Component
function tableApp() {
    return {
        searchQuery: '<?= htmlspecialchars($search) ?>',
        
        handleSearch(event) {
            // Debounced search handled by Alpine @input.debounce
            // Form submit will handle full search
        },
        
        clearSearch() {
            this.searchQuery = '';
            window.location.href = 'dashboard.php';
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
                    // Show loading
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    
                    // Redirect to delete
                    setTimeout(() => {
                        window.location.href = `hapus.php?id=${id}`;
                    }, 500);
                }
            });
        }
    };
}

// ============================================
// Counter Animation for Statistics
// ============================================
document.addEventListener('alpine:init', () => {
    Alpine.data('counter', () => ({
        count: 0,
        target: 0,
        init() {
            this.target = parseInt(this.$el.dataset.target) || 0;
            this.animateCount();
        },
        animateCount() {
            const duration = 1500;
            const steps = 60;
            const increment = this.target / steps;
            let current = 0;
            const timer = setInterval(() => {
                current += increment;
                if (current >= this.target) {
                    this.count = this.target;
                    clearInterval(timer);
                } else {
                    this.count = Math.floor(current);
                }
            }, duration / steps);
        }
    }));
});

// ============================================
// Keyboard Shortcuts
// ============================================
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K = Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }
    
    // Ctrl/Cmd + D = Toggle dark mode
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        const app = document.querySelector('[x-data]').__x;
        if (app) app.$data.toggleDarkMode();
    }
});

// ============================================
// Initialize tooltips (optional enhancement)
// ============================================
document.addEventListener('DOMContentLoaded', () => {
    // Add subtle entrance animation to table rows
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
    
    // Console greeting
    console.log('%c🎨 Dashboard Inventaris Sekolah', 'font-size: 20px; font-weight: bold; color: #1a365d;');
    console.log('%cKeyboard Shortcuts:', 'font-weight: bold; color: #2c5282;');
    console.log('  • Ctrl+K : Focus search');
    console.log('  • Ctrl+D : Toggle dark mode');
});
</script>

</body>
</html>