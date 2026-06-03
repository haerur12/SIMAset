<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);
$download = isset($_GET['download']) && $_GET['download'] == '1';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Statistik untuk Preview
$total_aset = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];
$total_pemerintah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Pemerintah'")->fetch_assoc()['total'];
$total_sekolah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Sekolah'")->fetch_assoc()['total'];
$total_nilai = mysqli_query($conn, "SELECT SUM(total) as total FROM inventaris")->fetch_assoc()['total'];

// Search untuk Preview
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "";
if($search && !$download) {
    $where = "WHERE spesifikasi_nama_barang LIKE '%$search%' OR nama_barang_108 LIKE '%$search%'";
}

// Query Data
$result = mysqli_query($conn, "SELECT * FROM inventaris $where ORDER BY created_at DESC");
$total_records = mysqli_num_rows($result);

// Hitung estimasi ukuran file
$estimated_size = ($total_records * 2.5) + 15; // KB (estimasi per row ~2.5KB + header 15KB)

// Header Excel jika download
if($download) {
    header("Content-type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=Inventaris_SDN_Curug1_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<?php if(!$download): ?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preview Export - Inventaris SDN Curug 01</title>
    
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
                        'bounce-soft': 'bounceSoft 2s infinite'
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(20px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                        slideInLeft: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(0)' } },
                        bounceSoft: { '0%, 100%': { transform: 'translateY(0)' }, '50%': { transform: 'translateY(-5px)' } }
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
        
        /* Excel-like table styling for preview */
        .excel-table {
            border-collapse: collapse;
            width: 100%;
            font-size: 12px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .excel-table thead th {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
            color: white;
            padding: 10px 8px;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border: 1px solid #0f2744;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .excel-table tbody td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 12px;
        }
        .dark .excel-table tbody td {
            border-color: #4a5568;
        }
        .excel-table tbody tr:nth-child(even) {
            background-color: #f7fafc;
        }
        .dark .excel-table tbody tr:nth-child(even) {
            background-color: rgba(45, 55, 72, 0.5);
        }
        .excel-table tbody tr:hover {
            background-color: #edf2f7 !important;
        }
        .dark .excel-table tbody tr:hover {
            background-color: rgba(74, 85, 104, 0.5) !important;
        }
        
        /* Excel badges */
        .excel-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
            color: white;
        }
        .excel-badge-pemerintah { background-color: #1a365d; }
        .excel-badge-sekolah { background-color: #d69e2e; }
        .excel-badge-bos { background-color: #38a169; }
        
        /* Grand total row */
        .grand-total-row {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 100%) !important;
            font-weight: 700;
        }
        .dark .grand-total-row {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%) !important;
        }
        
        /* Excel header preview */
        .excel-doc-header {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
        /* Download button glow */
        .download-btn {
            position: relative;
            overflow: hidden;
        }
        .download-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }
        .download-btn:hover::before {
            left: 100%;
        }
        
        /* File type indicator */
        .file-indicator {
            background: linear-gradient(135deg, #107c41 0%, #0d5c30 100%);
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="exportApp()">
    
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
                ['kategori_aset.php', 'fa-tags', 'Kategori Aset'],
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
                        <span class="text-primary font-semibold">Export Excel</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-file-excel text-emerald-600"></i>
                        <span>Export Excel</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <a href="dashboard.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all">
                    <i class="fas fa-arrow-left"></i>
                    <span class="text-sm font-medium">Kembali</span>
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Hero Download Section -->
            <div class="bg-gradient-to-br from-emerald-500 via-green-600 to-emerald-700 rounded-2xl shadow-xl p-6 lg:p-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
                <div class="absolute top-1/2 right-10 hidden lg:block opacity-20">
                    <i class="fas fa-file-excel text-[180px]"></i>
                </div>
                
                <div class="relative grid grid-cols-1 lg:grid-cols-3 gap-6 items-center">
                    <div class="lg:col-span-2">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-3 bg-white/20 backdrop-blur rounded-xl animate-bounce-soft">
                                <i class="fas fa-file-excel text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl lg:text-3xl font-bold">Export Data Inventaris</h3>
                                <p class="text-sm text-white/90">Download data dalam format Microsoft Excel (.xls)</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-3 mt-4">
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                                <i class="fas fa-database"></i>
                                <span><strong><?= number_format($total_records) ?></strong> data</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                                <i class="fas fa-weight-hanging"></i>
                                <span>~<strong><?= number_format($estimated_size, 1) ?></strong> KB</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                                <i class="fas fa-table-columns"></i>
                                <span><strong>18</strong> kolom</span>
                            </div>
                            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                                <i class="fas fa-calendar"></i>
                                <span><?= date('d M Y') ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3">
                        <button @click="confirmDownload()" 
                                class="download-btn w-full px-6 py-4 bg-white hover:bg-gray-50 text-emerald-700 rounded-xl shadow-lg hover:shadow-2xl transition-all flex items-center justify-center gap-3 font-bold text-base group">
                            <i class="fas fa-download text-xl group-hover:animate-bounce"></i>
                            <span>Download Excel</span>
                        </button>
                        <p class="text-xs text-white/80 text-center">
                            <i class="fas fa-shield-halved mr-1"></i>
                            File aman & terenkripsi
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6">
                <?php 
                $stats = [
                    ['Total Aset', $total_aset, 'fa-boxes-stacked', 'from-blue-500 to-blue-600'],
                    ['Aset Pemerintah', $total_pemerintah, 'fa-landmark', 'from-primary to-primary-dark'],
                    ['Aset Sekolah', $total_sekolah, 'fa-school', 'from-amber-500 to-amber-600'],
                    ['Total Nilai', formatRupiah($total_nilai), 'fa-coins', 'from-emerald-500 to-emerald-600'],
                ];
                foreach($stats as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-5 lg:p-6">
                        <div class="flex items-start justify-between mb-3">
                            <div class="p-3 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-lg"></i>
                            </div>
                        </div>
                        <p class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-2xl lg:text-3xl font-bold text-gray-800 dark:text-white truncate"><?= $stat[1] ?></h3>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Info Box -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-100 dark:border-blue-800 p-4 lg:p-5">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-500/10 rounded-lg flex-shrink-0">
                        <i class="fas fa-circle-info text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-blue-900 dark:text-blue-300 mb-2 flex items-center gap-2">
                            Informasi Export
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-blue-800 dark:text-blue-400">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Format file: Microsoft Excel (.xls)</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Kompatibel dengan Excel 2007+</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Data terupdate hingga hari ini</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Dilengkapi grand total otomatis</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <!-- Card Header -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-eye text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Preview Data</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Tampilan yang akan diexport ke Excel
                                    <?php if($search): ?>
                                        • Filter: <strong class="text-primary">"<?= htmlspecialchars($search) ?>"</strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Search Form -->
                        <form method="GET" class="flex gap-2 w-full lg:w-auto">
                            <input type="hidden" name="download" value="0">
                            <div class="relative flex-1 lg:w-80">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" 
                                       name="search" 
                                       placeholder="Cari nama/spesifikasi barang..." 
                                       value="<?= htmlspecialchars($search) ?>"
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                            </div>
                            <button type="submit" 
                                    class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                <i class="fas fa-filter"></i>
                                <span class="hidden sm:inline">Filter</span>
                            </button>
                            <?php if($search): ?>
                            <a href="export_excel.php" 
                               class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Excel Header Preview -->
                <div class="excel-doc-header">
                    <div class="flex items-center justify-center gap-3 mb-2">
                        <div class="w-12 h-12 bg-white/20 backdrop-blur rounded-lg flex items-center justify-center">
                            <i class="fas fa-school text-2xl"></i>
                        </div>
                        <div class="text-left">
                            <h3 class="text-xl lg:text-2xl font-bold">DATA INVENTARIS SEKOLAH</h3>
                            <p class="text-sm text-white/90">SDN CURUG 01 - BOJONGSARI</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-white/20 flex flex-wrap items-center justify-center gap-4 text-xs">
                        <span><i class="fas fa-calendar-alt mr-1"></i> Tanggal Export: <strong><?= date('d F Y') ?></strong></span>
                        <span><i class="fas fa-database mr-1"></i> Total: <strong><?= number_format($total_records) ?> data</strong></span>
                        <?php if($search): ?>
                        <span><i class="fas fa-filter mr-1"></i> Filter: <strong>"<?= htmlspecialchars($search) ?>"</strong></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">
                    <table class="excel-table">
                        <thead>
                            <tr>
                                <th class="text-center">No</th>
                                <th>Sumber</th>
                                <th>Kode Lokasi</th>
                                <th>Nama Unit</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Spesifikasi</th>
                                <th>Satuan</th>
                                <th class="text-center">Jumlah</th>
                                <th class="text-right">Harga Satuan</th>
                                <th class="text-right">Total</th>
                                <th>No Kontrak</th>
                                <th>Tgl Kontrak</th>
                                <th>No BAST</th>
                                <th>Tgl BAST</th>
                                <th>Nama PPK</th>
                                <th>Pengurus Barang</th>
                                <th>Keterangan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            $grand_total = 0;
                            while($row = mysqli_fetch_assoc($result)): 
                                $grand_total += $row['total'];
                                $badgeClass = $row['sumber_pengadaan'] == 'Pemerintah' ? 'excel-badge-pemerintah' : 
                                             ($row['sumber_pengadaan'] == 'Sekolah' ? 'excel-badge-sekolah' : 'excel-badge-bos');
                            ?>
                            <tr>
                                <td class="text-center"><?= $no++ ?></td>
                                <td><span class="excel-badge <?= $badgeClass ?>"><?= $row['sumber_pengadaan'] ?></span></td>
                                <td><?= htmlspecialchars($row['kode_lokasi']) ?></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><?= htmlspecialchars($row['nama_unit_lokasi']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['kode_barang_108']) ?></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><strong><?= htmlspecialchars($row['nama_barang_108']) ?></strong></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><?= htmlspecialchars($row['spesifikasi_nama_barang']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($row['satuan']) ?></td>
                                <td class="text-center"><?= $row['jumlah'] ?></td>
                                <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                                <td class="text-right"><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                                <td><?= htmlspecialchars($row['no_dokumen_kontrak']) ?></td>
                                <td class="text-center"><?= $row['tanggal_kontrak'] ? date('d/m/Y', strtotime($row['tanggal_kontrak'])) : '-' ?></td>
                                <td><?= htmlspecialchars($row['no_bast']) ?></td>
                                <td class="text-center"><?= $row['tanggal_bast'] ? date('d/m/Y', strtotime($row['tanggal_bast'])) : '-' ?></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><?= htmlspecialchars($row['nama_ppk']) ?></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><?= htmlspecialchars($row['nama_pengurus_barang']) ?></td>
                                <td style="max-width: 180px; word-wrap: break-word;"><?= htmlspecialchars($row['keterangan']) ?></td>
                            </tr>
                            <?php endwhile; ?>
                            
                            <?php if($total_records == 0): ?>
                            <tr>
                                <td colspan="18" class="text-center py-12">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data ditemukan</p>
                                        <p class="text-sm text-gray-400">Coba kata kunci pencarian lain</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr class="grand-total-row">
                                <td colspan="10" class="text-right" style="padding: 12px; font-size: 13px;">GRAND TOTAL:</td>
                                <td class="text-right" style="padding: 12px; font-size: 13px;">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                                <td colspan="6"></td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer Info -->
                <div class="p-4 lg:p-5 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                    <div class="flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex items-center gap-4">
                            <span><i class="fas fa-table-cells mr-1"></i> <?= $total_records ?> baris data</span>
                            <span><i class="fas fa-columns mr-1"></i> 18 kolom</span>
                            <span><i class="fas fa-calculator mr-1"></i> Grand Total: <strong class="text-primary">Rp <?= number_format($grand_total, 0, ',', '.') ?></strong></span>
                        </div>
                        <button @click="confirmDownload()" 
                                class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg transition-all flex items-center gap-2 text-xs font-semibold">
                            <i class="fas fa-download"></i>
                            Download Sekarang
                        </button>
                    </div>
                </div>
            </div>
            
        </div>
    </main>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function exportApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        init() {
            // Welcome toast
            setTimeout(() => {
                this.showToast('Preview siap! Klik Download untuk export', 'info');
            }, 1000);
        },
        
        confirmDownload() {
            const totalRecords = <?= $total_records ?>;
            const estimatedSize = <?= $estimated_size ?>;
            const searchFilter = '<?= addslashes($search) ?>';
            
            if (totalRecords === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Tidak Ada Data',
                    text: 'Tidak ada data yang bisa diexport. Silakan cek kembali filter pencarian.',
                    confirmButtonColor: '#1a365d'
                });
                return;
            }
            
            Swal.fire({
                title: '<i class="fas fa-file-excel text-emerald-500 mr-2"></i> Konfirmasi Download',
                html: `
                    <div class="text-left space-y-3 mt-4">
                        <div class="p-4 bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
                            <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i> Detail File
                            </p>
                            <div class="space-y-1.5 text-xs text-emerald-800 dark:text-emerald-400">
                                <div class="flex justify-between">
                                    <span><i class="fas fa-file-excel mr-2"></i> Format</span>
                                    <strong>Microsoft Excel (.xls)</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span><i class="fas fa-database mr-2"></i> Jumlah Data</span>
                                    <strong>${totalRecords.toLocaleString('id-ID')} baris</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span><i class="fas fa-weight-hanging mr-2"></i> Estimasi Ukuran</span>
                                    <strong>~${estimatedSize.toFixed(1)} KB</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span><i class="fas fa-table-columns mr-2"></i> Kolom</span>
                                    <strong>18 kolom</strong>
                                </div>
                                <div class="flex justify-between">
                                    <span><i class="fas fa-calendar mr-2"></i> Tanggal Export</span>
                                    <strong>${new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</strong>
                                </div>
                                ${searchFilter ? `
                                <div class="flex justify-between pt-1.5 border-t border-emerald-200 dark:border-emerald-800">
                                    <span><i class="fas fa-filter mr-2"></i> Filter</span>
                                    <strong class="truncate ml-2">"${searchFilter}"</strong>
                                </div>
                                ` : ''}
                            </div>
                        </div>
                        
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                            <p class="text-xs text-blue-800 dark:text-blue-300">
                                <i class="fas fa-lightbulb text-blue-500 mr-1"></i>
                                <strong>Tips:</strong> File akan otomatis ter-download ke folder Downloads Anda.
                            </p>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#107c41',
                cancelButtonColor: '#718096',
                confirmButtonText: '<i class="fas fa-download mr-1"></i> Ya, Download!',
                cancelButtonText: 'Batal',
                width: '500px'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Mempersiapkan File...',
                        html: `
                            <div class="flex flex-col items-center gap-3 mt-3">
                                <div class="relative">
                                    <i class="fas fa-file-excel text-5xl text-emerald-500 animate-bounce"></i>
                                    <i class="fas fa-spinner fa-spin text-2xl text-emerald-700 absolute -bottom-1 -right-1"></i>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-300">Mohon tunggu sebentar...</p>
                            </div>
                        `,
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        // Trigger download
                        const searchParam = searchFilter ? `&search=${encodeURIComponent(searchFilter)}` : '';
                        window.location.href = `export_excel.php?download=1${searchParam}`;
                        
                        // Show success toast after delay
                        setTimeout(() => {
                            this.showToast('File berhasil didownload!', 'success');
                        }, 1000);
                    });
                }
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

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // Ctrl/Cmd + D = Download
    if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
        e.preventDefault();
        const app = document.querySelector('[x-data]').__x;
        if (app) app.$data.confirmDownload();
    }
    // Ctrl/Cmd + K = Focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) searchInput.focus();
    }
});
</script>

</body>
</html>
<?php else: ?>
<!-- EXCEL OUTPUT MODE - Kolom dipertahankan persis seperti aslinya -->
<html xmlns:o="urn:schemas-microsoft-com:office:office"
      xmlns:x="urn:schemas-microsoft-com:office:excel"
      xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <!--[if gte mso 9]>
    <xml>
        <x:ExcelWorkbook>
            <x:ExcelWorksheets>
                <x:ExcelWorksheet>
                    <x:Name>Inventaris</x:Name>
                    <x:WorksheetOptions>
                        <x:DisplayGridlines/>
                    </x:WorksheetOptions>
                </x:ExcelWorksheet>
            </x:ExcelWorksheets>
        </x:ExcelWorkbook>
    </xml>
    <![endif]-->
    <style>
        .excel-header {
            text-align: center;
            padding: 15px;
            background: #1a365d;
            color: white;
            font-family: Arial, sans-serif;
        }
        .excel-header h3 { margin: 0 0 5px 0; font-size: 18px; }
        .excel-header p { margin: 0; font-size: 12px; }
        
        table.excel {
            border-collapse: collapse;
            width: 100%;
            font-family: Arial, sans-serif;
            font-size: 11px;
        }
        table.excel th {
            background-color: #1a365d;
            color: white;
            padding: 8px;
            font-weight: bold;
            border: 1px solid #0f2744;
            text-align: center;
        }
        table.excel td {
            padding: 6px 8px;
            border: 1px solid #cbd5e0;
            vertical-align: middle;
        }
        table.excel tr:nth-child(even) td {
            background-color: #f7fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .wrap { white-space: normal; word-wrap: break-word; max-width: 180px; }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            color: white;
        }
        .badge-pemerintah { background-color: #1a365d; }
        .badge-sekolah { background-color: #d69e2e; }
        .badge-bos { background-color: #38a169; }
        
        .grand-total {
            font-weight: bold;
            background-color: #e2e8f0 !important;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="excel-header">
        <h3>DATA INVENTARIS SEKOLAH - SDN CURUG 01</h3>
        <p>Tanggal Export: <?= date('d F Y') ?> <?= $search ? '| Filter: "'.$search.'"' : '' ?></p>
    </div>
    
    <table class="excel">
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Sumber</th>
                <th>Kode Lokasi</th>
                <th>Nama Unit</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Spesifikasi</th>
                <th>Satuan</th>
                <th class="text-center">Jumlah</th>
                <th class="text-right">Harga Satuan</th>
                <th class="text-right">Total</th>
                <th>No Kontrak</th>
                <th>Tgl Kontrak</th>
                <th>No BAST</th>
                <th>Tgl BAST</th>
                <th>Nama PPK</th>
                <th>Pengurus Barang</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            mysqli_data_seek($result, 0);
            $no = 1;
            $grand_total = 0;
            while($row = mysqli_fetch_assoc($result)): 
                $grand_total += $row['total'];
                $badgeClass = $row['sumber_pengadaan'] == 'Pemerintah' ? 'badge-pemerintah' : 
                             ($row['sumber_pengadaan'] == 'Sekolah' ? 'badge-sekolah' : 'badge-bos');
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><span class="badge <?= $badgeClass ?>"><?= $row['sumber_pengadaan'] ?></span></td>
                <td><?= $row['kode_lokasi'] ?></td>
                <td class="wrap"><?= $row['nama_unit_lokasi'] ?></td>
                <td class="text-center"><?= $row['kode_barang_108'] ?></td>
                <td class="wrap"><strong><?= $row['nama_barang_108'] ?></strong></td>
                <td class="wrap"><?= $row['spesifikasi_nama_barang'] ?></td>
                <td class="text-center"><?= $row['satuan'] ?></td>
                <td class="text-center"><?= $row['jumlah'] ?></td>
                <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right"><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                <td><?= $row['no_dokumen_kontrak'] ?></td>
                <td class="text-center"><?= $row['tanggal_kontrak'] ? date('d/m/Y', strtotime($row['tanggal_kontrak'])) : '-' ?></td>
                <td><?= $row['no_bast'] ?></td>
                <td class="text-center"><?= $row['tanggal_bast'] ? date('d/m/Y', strtotime($row['tanggal_bast'])) : '-' ?></td>
                <td class="wrap"><?= $row['nama_ppk'] ?></td>
                <td class="wrap"><?= $row['nama_pengurus_barang'] ?></td>
                <td class="wrap"><?= $row['keterangan'] ?></td>
            </tr>
            <?php endwhile; ?>
            <tr class="grand-total">
                <td colspan="10" class="text-right">GRAND TOTAL:</td>
                <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                <td colspan="6"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
<?php endif; ?>