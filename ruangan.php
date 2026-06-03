<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Hapus Ruangan
if(isset($_GET['hapus'])) {
    // ✅ PROTEKSI: Hanya admin yang bisa hapus
    requireAccess('delete', 'ruangan.php');

    $id = (int)$_GET['hapus'];
    $stmt = mysqli_prepare($conn, "DELETE FROM ruangan WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if(mysqli_stmt_execute($stmt)) {
        header("Location: ruangan.php?action=deleted");
        exit;
    } else {
        header("Location: ruangan.php?action=error");
        exit;
    }
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search & Filter
$search = isset($_GET['cari']) ? mysqli_real_escape_string($conn, $_GET['cari']) : '';
$filter_gedung = isset($_GET['gedung']) ? mysqli_real_escape_string($conn, $_GET['gedung']) : '';

$where_clauses = [];
if($search) {
    $where_clauses[] = "(nama_ruangan LIKE '%$search%' OR kode_ruangan LIKE '%$search%')";
}
if($filter_gedung) {
    $where_clauses[] = "gedung = '$filter_gedung'";
}

$where = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

$result = mysqli_query($conn, "SELECT * FROM ruangan $where ORDER BY kode_ruangan ASC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM ruangan $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Statistik
$total_ruangan = mysqli_query($conn, "SELECT COUNT(*) as total FROM ruangan")->fetch_assoc()['total'];
$total_gedung = mysqli_query($conn, "SELECT COUNT(DISTINCT gedung) as total FROM ruangan")->fetch_assoc()['total'];
$total_kapasitas = mysqli_query($conn, "SELECT SUM(kapasitas) as total FROM ruangan")->fetch_assoc()['total'];

// Get unique gedung for filter
$gedung_list = mysqli_query($conn, "SELECT DISTINCT gedung FROM ruangan WHERE gedung IS NOT NULL AND gedung != '' ORDER BY gedung ASC");
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Ruangan - Inventaris SDN Curug 01</title>
    
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
        
        /* Stagger animation for cards */
        .stagger-item { animation: slideUp 0.5s ease-out backwards; }
        .stagger-item:nth-child(1) { animation-delay: 0.1s; }
        .stagger-item:nth-child(2) { animation-delay: 0.2s; }
        .stagger-item:nth-child(3) { animation-delay: 0.3s; }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="ruanganApp()">
    
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
   <?php include 'sidebar.php'; ?>
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
                        <i class="fas fa-door-open"></i>
                        <span>Manajemen Ruangan</span>
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
                
                <!-- Add Room Button -->
                <?php if(canCreate()): ?>
                <a href="tambah_ruangan.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle"></i>
                    <span class="text-sm font-medium">Tambah Ruangan</span>
                </a>
                <?php endif; ?>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 lg:gap-6">
                <?php 
                $stats = [
                    ['Total Ruangan', $total_ruangan, 'fa-door-open', 'from-blue-500 to-blue-600', 'Jumlah keseluruhan ruangan'],
                    ['Total Gedung', $total_gedung, 'fa-building', 'from-emerald-500 to-emerald-600', 'Jumlah gedung berbeda'],
                    ['Total Kapasitas', $total_kapasitas, 'fa-users', 'from-amber-500 to-amber-600', 'Kapasitas total orang'],
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
                        <h3 class="text-2xl lg:text-3xl font-bold text-gray-800 dark:text-white">
                            <?= number_format($stat[1]) ?>
                            <?php if($idx == 2): ?>
                                <span class="text-sm font-normal text-gray-500 ml-1">Orang</span>
                            <?php endif; ?>
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
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Daftar Ruangan</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Menampilkan <span class="font-semibold text-primary"><?= $total_records ?></span> data
                                </p>
                            </div>
                        </div>
                        
                        <!-- Search & Filter -->
                        <form method="GET" class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                            <!-- Gedung Filter -->
                            <select name="gedung" 
                                    @change="$el.form.submit()"
                                    class="px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                <option value="">Semua Gedung</option>
                                <?php while($gedung = mysqli_fetch_assoc($gedung_list)): ?>
                                    <option value="<?= htmlspecialchars($gedung['gedung']) ?>" 
                                            <?= $filter_gedung == $gedung['gedung'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($gedung['gedung']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            
                            <!-- Search Input -->
                            <div class="relative flex-1 lg:w-80">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" 
                                       name="cari" 
                                       x-model="searchQuery"
                                       placeholder="Cari nama/kode ruangan..." 
                                       value="<?= htmlspecialchars($search) ?>"
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                                <button x-show="searchQuery" @click="clearSearch()" type="button"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500">
                                    <i class="fas fa-times-circle"></i>
                                </button>
                            </div>
                            
                            <button type="submit" 
                                    class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                <i class="fas fa-search"></i>
                                <span>Cari</span>
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
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Kode</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Nama Ruangan</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Gedung</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Lantai</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Kapasitas</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Fungsi</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Penanggung Jawab</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if($total_records == 0): ?>
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data ditemukan</p>
                                        <p class="text-sm text-gray-400">Coba kata kunci pencarian atau filter lain</p>
                                        <a href="tambah_ruangan.php" class="mt-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all">
                                            <i class="fas fa-plus mr-2"></i>Tambah Ruangan Baru
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors group">
                                <td class="px-4 py-4 text-sm font-medium text-gray-600 dark:text-gray-300"><?= $no++ ?></td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-primary/10 text-primary border border-primary/20">
                                        <i class="fas fa-qrcode text-[10px] mr-1.5"></i>
                                        <?= htmlspecialchars($row['kode_ruangan']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm font-semibold text-gray-800 dark:text-white">
                                        <?= htmlspecialchars($row['nama_ruangan']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <i class="fas fa-building text-primary/60"></i>
                                        <?= htmlspecialchars($row['gedung']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-700 border border-blue-200">
                                        <i class="fas fa-layer-group text-[10px] mr-1"></i>
                                        Lantai <?= $row['lantai'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-200">
                                        <i class="fas fa-users text-amber-500 text-xs"></i>
                                        <?= $row['kapasitas'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-300 max-w-xs truncate" 
                                         title="<?= htmlspecialchars($row['fungsi_ruangan']) ?>">
                                        <?= htmlspecialchars($row['fungsi_ruangan']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                                            <i class="fas fa-user text-primary text-xs"></i>
                                        </div>
                                        <?= htmlspecialchars($row['penanggung_jawab']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-2">
                                        <?php if(canUpdate()): ?>
                                        <a href="edit_ruangan.php?id=<?= $row['id'] ?>" 
                                           class="p-2 bg-amber-100 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg transition-all duration-200 transform hover:scale-110"
                                           title="Edit">
                                            <i class="fas fa-edit text-sm"></i>
                                        </a>
                                        <?php endif; ?>
                                        <?php if(canDelete()): ?>
                                        <button @click="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_ruangan'])) ?>')"
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
                        </p>
                        <nav class="flex items-center gap-1">
                            <!-- Previous -->
                            <?php if($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&cari=<?= urlencode($search) ?>&gedung=<?= urlencode($filter_gedung) ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if($start_page > 1): ?>
                                <a href="?page=1&cari=<?= urlencode($search) ?>&gedung=<?= urlencode($filter_gedung) ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">1</a>
                                <?php if($start_page > 2): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                                <a href="?page=<?= $i ?>&cari=<?= urlencode($search) ?>&gedung=<?= urlencode($filter_gedung) ?>" 
                                   class="px-3 py-2 text-sm rounded-lg transition-all <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-primary hover:text-white hover:border-primary' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if($end_page < $total_pages): ?>
                                <?php if($end_page < $total_pages - 1): ?>
                                    <span class="px-2 text-gray-400">...</span>
                                <?php endif; ?>
                                <a href="?page=<?= $total_pages ?>&cari=<?= urlencode($search) ?>&gedung=<?= urlencode($filter_gedung) ?>" 
                                   class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all"><?= $total_pages ?></a>
                            <?php endif; ?>
                            
                            <!-- Next -->
                            <?php if($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>&cari=<?= urlencode($search) ?>&gedung=<?= urlencode($filter_gedung) ?>" 
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
// Modern JavaScript - Ruangan Application
// ============================================

// Alpine.js - Main Ruangan Component
function ruanganApp() {
    return {
        init() {
            // Check for URL parameters (success/error messages)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'deleted') {
                this.showToast('Ruangan berhasil dihapus!', 'success');
            } else if (urlParams.get('action') === 'added') {
                this.showToast('Ruangan berhasil ditambahkan!', 'success');
            } else if (urlParams.get('action') === 'updated') {
                this.showToast('Ruangan berhasil diperbarui!', 'success');
            } else if (urlParams.get('action') === 'error') {
                this.showToast('Terjadi kesalahan!', 'error');
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
                            <p class="text-sm font-semibold text-blue-900">Manajemen Ruangan</p>
                            <p class="text-xs text-blue-700 mt-1">Kelola semua ruangan sekolah dengan mudah.</p>
                        </div>
                        <div class="p-3 bg-amber-50 rounded-lg border-l-4 border-amber-500">
                            <p class="text-sm font-semibold text-amber-900">Tips</p>
                            <p class="text-xs text-amber-700 mt-1">Gunakan filter gedung untuk mempercepat pencarian.</p>
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
        
        clearSearch() {
            this.searchQuery = '';
            window.location.href = 'ruangan.php';
        },
        
        confirmDelete(id, name) {
            Swal.fire({
                title: 'Hapus Ruangan?',
                html: `
                    <p class="text-gray-600">Apakah Anda yakin ingin menghapus ruangan:</p>
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
                        window.location.href = `?hapus=${id}`;
                    }, 500);
                }
            });
        }
    };
}

// ============================================
// Keyboard Shortcuts
// ============================================
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + K = Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="cari"]');
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
// Initialize animations
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
    console.log('%c🏫 Manajemen Ruangan', 'font-size: 20px; font-weight: bold; color: #1a365d;');
    console.log('%cKeyboard Shortcuts:', 'font-weight: bold; color: #2c5282;');
    console.log('  • Ctrl+K : Focus search');
    console.log('  • Ctrl+D : Toggle dark mode');
});
</script>

</body>
</html>