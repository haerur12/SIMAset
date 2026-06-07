<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ TAMBAH TRACKING MANUAL
if(isset($_POST['tambah_tracking'])) {
    requireAccess('create', 'tracking_aset.php');
    $inventaris_id = (int)$_POST['inventaris_id'];
    $tanggal = mysqli_real_escape_string($conn, $_POST['tanggal_tracking']);
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_tracking']);
    $dari = mysqli_real_escape_string($conn, trim($_POST['dari_lokasi']));
    $ke = mysqli_real_escape_string($conn, trim($_POST['ke_lokasi']));
    $ket = mysqli_real_escape_string($conn, trim($_POST['keterangan']));
    $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
    
    $stmt = mysqli_prepare($conn, "INSERT INTO tracking_aset (inventaris_id, tanggal_tracking, jenis_tracking, dari_lokasi, ke_lokasi, keterangan, petugas) VALUES (?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "issssss", $inventaris_id, $tanggal, $jenis, $dari, $ke, $ket, $petugas);
    
    if(mysqli_stmt_execute($stmt)) {
        // Jika jenis tracking adalah "Pindah Ruangan", update ruangan di tabel inventaris
        if($jenis == 'Pindah Ruangan' && !empty($ke)) {
            $ke_esc = mysqli_real_escape_string($conn, $ke);
            $ruangan_baru = mysqli_query($conn, "SELECT id FROM ruangan WHERE nama_ruangan = '$ke_esc' LIMIT 1");
            if($ruangan_baru && $ruangan_baru->num_rows > 0) {
                $r = $ruangan_baru->fetch_assoc();
                mysqli_query($conn, "UPDATE inventaris SET ruangan_id = {$r['id']} WHERE id = $inventaris_id");
            }
        }
        header("Location: tracking_aset.php?action=added");
        exit;
    } else {
        header("Location: tracking_aset.php?action=error");
        exit;
    }
}

// ✅ HAPUS TRACKING
if(isset($_GET['hapus'])) {
    requireAccess('delete', 'tracking_aset.php');
    $id = (int)$_GET['hapus'];
    $stmt = mysqli_prepare($conn, "DELETE FROM tracking_aset WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if(mysqli_stmt_execute($stmt)) {
        header("Location: tracking_aset.php?action=deleted");
        exit;
    } else {
        header("Location: tracking_aset.php?action=error");
        exit;
    }
}

// ✅ PAGINATION & FILTER
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$filter_jenis = isset($_GET['jenis']) ? mysqli_real_escape_string($conn, $_GET['jenis']) : '';
$search = isset($_GET['cari']) ? mysqli_real_escape_string($conn, $_GET['cari']) : '';

$where = [];
if($filter_jenis) {
    $where[] = "t.jenis_tracking = '$filter_jenis'";
}
if($search) {
    $where[] = "(i.nama_barang_108 LIKE '%$search%' OR t.keterangan LIKE '%$search%' OR t.petugas LIKE '%$search%' OR p.peminjam LIKE '%$search%')";
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : "";

$query = "SELECT t.*, i.nama_barang_108, i.spesifikasi_nama_barang, i.kode_barang_108,
                 r.nama_ruangan as current_ruangan,
                 p.peminjam, p.status as status_peminjaman, p.tanggal_kembali_rencana
          FROM tracking_aset t 
          LEFT JOIN inventaris i ON t.inventaris_id = i.id 
          LEFT JOIN ruangan r ON i.ruangan_id = r.id
          LEFT JOIN peminjaman_aset p ON t.peminjaman_id = p.id
          $where_clause 
          ORDER BY t.tanggal_tracking DESC, t.created_at DESC 
          LIMIT $start, $limit";

$result = mysqli_query($conn, $query);
if (!$result) {
    error_log("tracking_aset Query Error: " . mysqli_error($conn));
    $result = mysqli_query($conn, "SELECT id FROM tracking_aset WHERE 0"); // empty result fallback
}

$countRes = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset t LEFT JOIN inventaris i ON t.inventaris_id = i.id LEFT JOIN peminjaman_aset p ON t.peminjaman_id = p.id $where_clause");
$total_records = $countRes ? (int)mysqli_fetch_assoc($countRes)['total'] : 0;
$total_pages = ceil($total_records / $limit);

// ✅ STATISTIK (dengan tracking otomatis dari peminjaman)
$statsRes = mysqli_query($conn, "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN jenis_tracking = 'Pindah Ruangan' THEN 1 ELSE 0 END) as pindah,
    SUM(CASE WHEN jenis_tracking = 'Mutasi' THEN 1 ELSE 0 END) as mutasi,
    SUM(CASE WHEN jenis_tracking = 'Peminjaman' THEN 1 ELSE 0 END) as pinjam,
    SUM(CASE WHEN jenis_tracking = 'Pengembalian' THEN 1 ELSE 0 END) as kembali,
    SUM(CASE WHEN jenis_tracking = 'Perpanjangan' THEN 1 ELSE 0 END) as perpanjang,
    SUM(CASE WHEN jenis_tracking = 'Perbaikan' THEN 1 ELSE 0 END) as perbaikan,
    SUM(CASE WHEN peminjaman_id IS NOT NULL THEN 1 ELSE 0 END) as auto_tracking
    FROM tracking_aset");
$stats = $statsRes ? mysqli_fetch_assoc($statsRes) : ['total'=>0,'pindah'=>0,'mutasi'=>0,'pinjam'=>0,'kembali'=>0,'perpanjang'=>0,'perbaikan'=>0,'auto_tracking'=>0];

// ✅ Statistik Peminjaman Aktif (untuk widget)
$spRes = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status IN ('dipinjam', 'terlambat')");
$stat_peminjaman_aktif = $spRes ? (int)mysqli_fetch_assoc($spRes)['total'] : 0;
$stRes = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'terlambat' OR (status = 'dipinjam' AND tanggal_kembali_rencana < CURDATE())");
$stat_peminjaman_terlambat = $stRes ? (int)mysqli_fetch_assoc($stRes)['total'] : 0;

// Get list of ruangan for form
$ruangan_list = mysqli_query($conn, "SELECT id, nama_ruangan, kode_ruangan FROM ruangan ORDER BY nama_ruangan ASC");

// Helper function for tracking style
function getTrackingStyle($jenis) {
    $styles = [
        'Pindah Ruangan' => ['icon' => 'fa-right-left', 'gradient' => 'from-emerald-500 to-green-600', 'bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-700 dark:text-emerald-300', 'border' => 'border-emerald-200 dark:border-emerald-800'],
        'Mutasi' => ['icon' => 'fa-shuffle', 'gradient' => 'from-amber-500 to-orange-600', 'bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-700 dark:text-amber-300', 'border' => 'border-amber-200 dark:border-amber-800'],
        'Peminjaman' => ['icon' => 'fa-hand-holding', 'gradient' => 'from-blue-500 to-indigo-600', 'bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-700 dark:text-blue-300', 'border' => 'border-blue-200 dark:border-blue-800'],
        'Pengembalian' => ['icon' => 'fa-rotate-left', 'gradient' => 'from-purple-500 to-pink-600', 'bg' => 'bg-purple-100 dark:bg-purple-900/30', 'text' => 'text-purple-700 dark:text-purple-300', 'border' => 'border-purple-200 dark:border-purple-800'],
        'Perpanjangan' => ['icon' => 'fa-calendar-plus', 'gradient' => 'from-sky-500 to-blue-600', 'bg' => 'bg-sky-100 dark:bg-sky-900/30', 'text' => 'text-sky-700 dark:text-sky-300', 'border' => 'border-sky-200 dark:border-sky-800'],
        'Perbaikan' => ['icon' => 'fa-screwdriver-wrench', 'gradient' => 'from-red-500 to-rose-600', 'bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-700 dark:text-red-300', 'border' => 'border-red-200 dark:border-red-800'],
    ];
    return $styles[$jenis] ?? $styles['Mutasi'];
}

function formatTanggalLocal($tgl) {
    if(!$tgl) return '-';
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $time = strtotime($tgl);
    return date('d', $time) . ' ' . $bulan[date('n', $time) - 1] . ' ' . date('Y', $time);
}
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Aset - Inventaris SDN Curug 01</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { primary: { DEFAULT: '#1a365d', dark: '#0f2744', light: '#2c5282' } },
                    fontFamily: { sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'] },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.4s ease-out',
                        'slide-in-left': 'slideInLeft 0.3s ease-out',
                        'pulse-dot': 'pulseDot 2s infinite'
                    },
                    keyframes: {
                        fadeIn: { '0%': { opacity: '0' }, '100%': { opacity: '1' } },
                        slideUp: { '0%': { transform: 'translateY(30px)', opacity: '0' }, '100%': { transform: 'translateY(0)', opacity: '1' } },
                        slideInLeft: { '0%': { transform: 'translateX(-100%)' }, '100%': { transform: 'translateX(0)' } },
                        pulseDot: { '0%, 100%': { opacity: '1', transform: 'scale(1)' }, '50%': { opacity: '0.7', transform: 'scale(1.2)' } }
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
        
        .timeline-item { position: relative; }
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 27px;
            top: 70px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #1a365d, transparent);
        }
        .dark .timeline-item:not(:last-child)::before {
            background: linear-gradient(to bottom, #4a5568, transparent);
        }
        
        .flow-arrow { animation: flowPulse 2s infinite; }
        @keyframes flowPulse {
            0%, 100% { transform: translateX(0); opacity: 1; }
            50% { transform: translateX(4px); opacity: 0.7; }
        }
        
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
        
        /* Badge auto-tracking */
        .badge-auto {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            animation: pulse 2s infinite;
        }
        
        /* Link peminjaman */
        .link-peminjaman {
            transition: all 0.2s;
        }
        .link-peminjaman:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="trackingApp()">
    
    <div x-show="sidebarOpen" 
         x-transition
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
                        <span class="text-primary font-semibold">Tracking Aset</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-route"></i>
                        <span>Tracking Aset</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <?php if(canCreate()): ?>
                <button @click="openAddModal()" 
                        class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all duration-200 transform hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle"></i>
                    <span class="text-sm font-medium">Tambah Tracking</span>
                </button>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- ✅ WIDGET PEMINJAMAN AKTIF (Integrasi dengan modul Peminjaman) -->
            <?php if($stat_peminjaman_aktif > 0): ?>
            <div class="bg-gradient-to-r from-blue-500 via-indigo-500 to-purple-600 rounded-2xl shadow-lg p-5 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
                
                <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                            <i class="fas fa-hand-holding-heart text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-lg">Peminjaman Aset Aktif</h3>
                            <p class="text-sm text-white/80">Monitoring peminjaman dari modul Peminjaman</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <div class="text-center px-4 py-2 bg-white/15 backdrop-blur rounded-lg">
                            <p class="text-2xl font-bold"><?= $stat_peminjaman_aktif ?></p>
                            <p class="text-[10px] uppercase text-white/80">Aktif</p>
                        </div>
                        <?php if($stat_peminjaman_terlambat > 0): ?>
                        <div class="text-center px-4 py-2 bg-red-500/30 backdrop-blur rounded-lg animate-pulse">
                            <p class="text-2xl font-bold"><?= $stat_peminjaman_terlambat ?></p>
                            <p class="text-[10px] uppercase text-white/80">Terlambat</p>
                        </div>
                        <?php endif; ?>
                        <a href="peminjaman.php" 
                           class="px-4 py-2 bg-white/20 hover:bg-white/30 backdrop-blur rounded-lg transition-all flex items-center gap-2 text-sm font-medium">
                            <span>Buka Modul</span>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 lg:gap-4">
                <?php 
                $stats_cards = [
                    ['Total', $stats['total'] ?? 0, 'fa-route', 'from-primary to-primary-light', 'Semua riwayat'],
                    ['Pindah', $stats['pindah'] ?? 0, 'fa-right-left', 'from-emerald-500 to-green-600', 'Perpindahan'],
                    ['Peminjaman', $stats['pinjam'] ?? 0, 'fa-hand-holding', 'from-blue-500 to-indigo-600', 'Dari modul'],
                    ['Pengembalian', $stats['kembali'] ?? 0, 'fa-rotate-left', 'from-purple-500 to-pink-600', 'Dari modul'],
                    ['Perpanjangan', $stats['perpanjang'] ?? 0, 'fa-calendar-plus', 'from-sky-500 to-blue-600', 'Dari modul'],
                    ['Perbaikan', $stats['perbaikan'] ?? 0, 'fa-screwdriver-wrench', 'from-red-500 to-rose-600', 'Maintenance'],
                ];
                foreach($stats_cards as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1 cursor-pointer"
                     @click="filterBy('<?= $idx === 0 ? '' : $stat[0] ?>')">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-3 lg:p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div class="p-2 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-xs"></i>
                            </div>
                        </div>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1 line-clamp-1"><?= $stat[0] ?></p>
                        <h3 class="text-xl lg:text-2xl font-bold text-gray-800 dark:text-white"><?= number_format($stat[1]) ?></h3>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Info Integrasi -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl border border-blue-200 dark:border-blue-800 p-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-blue-500/10 rounded-lg flex-shrink-0">
                        <i class="fas fa-link text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-blue-900 dark:text-blue-300 mb-1 flex items-center gap-2">
                            Terintegrasi dengan Modul Peminjaman
                            <span class="text-[10px] px-2 py-0.5 bg-green-500 text-white rounded-full">AUTO</span>
                        </h4>
                        <p class="text-xs text-blue-800 dark:text-blue-400">
                            Setiap aksi di modul <strong>Peminjaman</strong> (pinjam, kembalikan, perpanjang) otomatis tercatat di timeline tracking ini. 
                            Tracking dengan badge <span class="badge-auto px-1.5 py-0.5 rounded text-[10px] font-bold">AUTO</span> dibuat otomatis oleh sistem.
                        </p>
                    </div>
                    <a href="peminjaman.php" class="hidden sm:flex items-center gap-1 px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-semibold transition-all">
                        <span>Buka Modul</span>
                        <i class="fas fa-external-link-alt text-[10px]"></i>
                    </a>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 lg:p-5">
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="tracking_aset.php<?= $search ? '?cari=' . urlencode($search) : '' ?>" 
                       class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all <?= !$filter_jenis ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                        <i class="fas fa-layer-group mr-1"></i> Semua
                    </a>
                    <?php 
                    $filter_types = [
                        'Pindah Ruangan' => ['icon' => 'fa-right-left'],
                        'Mutasi' => ['icon' => 'fa-shuffle'],
                        'Peminjaman' => ['icon' => 'fa-hand-holding'],
                        'Pengembalian' => ['icon' => 'fa-rotate-left'],
                        'Perpanjangan' => ['icon' => 'fa-calendar-plus'],
                        'Perbaikan' => ['icon' => 'fa-screwdriver-wrench'],
                    ];
                    foreach($filter_types as $type => $meta): ?>
                    <a href="?jenis=<?= urlencode($type) ?><?= $search ? '&cari=' . urlencode($search) : '' ?>" 
                       class="filter-pill px-4 py-2 rounded-full text-xs font-semibold transition-all flex items-center gap-1.5 <?= $filter_jenis === $type ? 'active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600' ?>">
                        <i class="fas <?= $meta['icon'] ?>"></i>
                        <span><?= $type ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <form method="GET" class="flex flex-col sm:flex-row gap-2">
                    <?php if($filter_jenis): ?>
                    <input type="hidden" name="jenis" value="<?= htmlspecialchars($filter_jenis) ?>">
                    <?php endif; ?>
                    <div class="relative flex-1">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" 
                               name="cari" 
                               placeholder="Cari nama aset, peminjam, keterangan, atau petugas..." 
                               value="<?= htmlspecialchars($search) ?>"
                               class="w-full pl-11 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                    </div>
                    <button type="submit" 
                            class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                        <i class="fas fa-filter"></i>
                        <span>Filter</span>
                    </button>
                    <?php if($search || $filter_jenis): ?>
                    <a href="tracking_aset.php" 
                       class="px-5 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                        <i class="fas fa-times"></i>
                        <span>Reset</span>
                    </a>
                    <?php endif; ?>
                </form>
                
                <?php if($search || $filter_jenis): ?>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-3">
                    <i class="fas fa-info-circle mr-1"></i>
                    Menampilkan 
                    <?php if($filter_jenis): ?><strong class="text-primary"><?= htmlspecialchars($filter_jenis) ?></strong><?php endif; ?>
                    <?php if($search): ?>dengan kata kunci <strong class="text-primary">"<?= htmlspecialchars($search) ?>"</strong><?php endif; ?>
                    (<?= $total_records ?> ditemukan)
                </p>
                <?php endif; ?>
            </div>
            
            <!-- Timeline List -->
            <div class="space-y-4">
                <?php if(mysqli_num_rows($result) > 0): 
                    $last_date = null;
                    while($row = mysqli_fetch_assoc($result)): 
                        $style = getTrackingStyle($row['jenis_tracking']);
                        $current_date = date('Y-m-d', strtotime($row['tanggal_tracking']));
                        $show_date_header = ($current_date !== $last_date);
                        $last_date = $current_date;
                        $is_auto = !empty($row['peminjaman_id']);
                ?>
                
                <!-- Date Header -->
                <?php if($show_date_header): ?>
                <div class="flex items-center gap-3 mt-6 mb-2">
                    <div class="w-14 h-14 rounded-full bg-gradient-to-br from-primary to-primary-light flex items-center justify-center text-white shadow-lg flex-shrink-0">
                        <div class="text-center">
                            <div class="text-xs font-bold leading-none"><?= date('d', strtotime($row['tanggal_tracking'])) ?></div>
                            <div class="text-[9px] uppercase"><?= date('M', strtotime($row['tanggal_tracking'])) ?></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-bold text-gray-800 dark:text-white text-sm">
                            <?= formatTanggalLocal($row['tanggal_tracking']) ?>
                        </h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            <?php 
                            $day_name = date('l', strtotime($row['tanggal_tracking']));
                            $days = ['Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'];
                            echo $days[$day_name] ?? $day_name;
                            ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Timeline Item -->
                <div class="stagger-item timeline-item">
                    <div class="flex gap-4">
                        <!-- Timeline Dot -->
                        <div class="flex flex-col items-center flex-shrink-0">
                            <div class="w-14 h-14 rounded-full bg-gradient-to-br <?= $style['gradient'] ?> flex items-center justify-center text-white shadow-lg ring-4 ring-white dark:ring-gray-800 relative">
                                <i class="fas <?= $style['icon'] ?> text-lg"></i>
                                <?php if($is_auto): ?>
                                <span class="absolute -top-1 -right-1 w-5 h-5 bg-green-500 border-2 border-white dark:border-gray-800 rounded-full flex items-center justify-center">
                                    <i class="fas fa-bolt text-[8px] text-white"></i>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Card -->
                        <div class="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <!-- Card Header -->
                            <div class="p-4 lg:p-5 border-b border-gray-100 dark:border-gray-700 bg-gradient-to-r from-gray-50 to-transparent dark:from-gray-700/30">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold <?= $style['bg'] ?> <?= $style['text'] ?> border <?= $style['border'] ?>">
                                                <i class="fas <?= $style['icon'] ?> text-[10px]"></i>
                                                <?= htmlspecialchars($row['jenis_tracking']) ?>
                                            </span>
                                            
                                            <!-- ✅ Badge AUTO jika dari modul peminjaman -->
                                            <?php if($is_auto): ?>
                                            <span class="badge-auto inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-bold">
                                                <i class="fas fa-bolt text-[8px]"></i>
                                                AUTO
                                            </span>
                                            <?php endif; ?>
                                            
                                            <!-- ✅ Badge Status Peminjaman (jika terkait) -->
                                            <?php if(!empty($row['status_peminjaman'])): 
                                                $status_badge = [
                                                    'dipinjam' => ['bg-blue-100 text-blue-700 border-blue-200', 'fa-clock', 'Dipinjam'],
                                                    'terlambat' => ['bg-red-100 text-red-700 border-red-200', 'fa-exclamation-triangle', 'Terlambat'],
                                                    'dikembalikan' => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'fa-check-circle', 'Selesai'],
                                                ];
                                                $sb = $status_badge[$row['status_peminjaman']] ?? null;
                                                if($sb):
                                            ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[10px] font-semibold <?= $sb[0] ?> border">
                                                <i class="fas <?= $sb[1] ?> text-[8px]"></i>
                                                <?= $sb[2] ?>
                                            </span>
                                            <?php endif; endif; ?>
                                            
                                            <span class="text-xs text-gray-400">
                                                <i class="far fa-clock mr-1"></i>
                                                <?= date('H:i', strtotime($row['created_at'] ?? 'now')) ?> WIB
                                            </span>
                                        </div>
                                        <h4 class="font-bold text-gray-800 dark:text-white truncate">
                                            <?= htmlspecialchars($row['nama_barang_108'] ?? 'Aset tidak ditemukan') ?>
                                        </h4>
                                        <?php if($row['spesifikasi_nama_barang']): ?>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">
                                            <?= htmlspecialchars(substr($row['spesifikasi_nama_barang'], 0, 60)) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-1 flex-shrink-0">
                                        <!-- ✅ Link ke Detail Peminjaman (jika terkait) -->
                                        <?php if($is_auto): ?>
                                        <a href="peminjaman.php?tab=semua&cari=<?= urlencode($row['peminjam'] ?? '') ?>" 
                                           class="link-peminjaman p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all"
                                           title="Lihat Detail Peminjaman">
                                            <i class="fas fa-hand-holding text-xs"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(canDelete()): ?>
                                        <button @click="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['nama_barang_108'] ?? 'Aset')) ?>')"
                                                class="p-2 bg-red-100 hover:bg-red-500 text-red-600 hover:text-white rounded-lg transition-all"
                                                title="Hapus">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Flow: Dari → Ke -->
                            <div class="p-4 lg:p-5">
                                <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-3 items-center">
                                    <!-- Dari -->
                                    <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-600">
                                        <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1 flex items-center gap-1">
                                            <i class="fas fa-sign-out-alt text-xs"></i> Dari
                                        </p>
                                        <p class="text-sm font-semibold text-gray-800 dark:text-white truncate">
                                            <?= htmlspecialchars($row['dari_lokasi'] ?: 'Tidak diketahui') ?>
                                        </p>
                                        <?php if(in_array($row['jenis_tracking'], ['Peminjaman', 'Pengembalian', 'Perpanjangan']) && !empty($row['peminjam'])): ?>
                                        <p class="text-[10px] text-blue-600 dark:text-blue-400 mt-1 flex items-center gap-1">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($row['peminjam']) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Arrow -->
                                    <div class="flex items-center justify-center">
                                        <div class="flow-arrow flex items-center gap-1 text-primary">
                                            <div class="hidden md:block h-0.5 w-8 bg-gradient-to-r from-primary to-primary-light"></div>
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                    </div>
                                    
                                    <!-- Ke -->
                                    <div class="p-3 bg-gradient-to-br from-primary/5 to-primary/10 dark:from-primary/20 dark:to-primary/10 rounded-lg border-2 border-primary/20 dark:border-primary/30">
                                        <p class="text-[10px] uppercase tracking-wider text-primary dark:text-primary-light font-semibold mb-1 flex items-center gap-1">
                                            <i class="fas fa-sign-in-alt text-xs"></i> Ke
                                        </p>
                                        <p class="text-sm font-bold text-primary dark:text-primary-light truncate">
                                            <?= htmlspecialchars($row['ke_lokasi'] ?: 'Tidak diketahui') ?>
                                        </p>
                                        <?php if($row['jenis_tracking'] === 'Peminjaman' && !empty($row['tanggal_kembali_rencana'])): ?>
                                        <p class="text-[10px] text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                                            <i class="fas fa-calendar-alt"></i>
                                            Kembali: <?= date('d/m/Y', strtotime($row['tanggal_kembali_rencana'])) ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Keterangan & Petugas -->
                                <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center gap-2 text-xs">
                                    <?php if($row['keterangan']): ?>
                                    <div class="flex-1 flex items-start gap-2 text-gray-600 dark:text-gray-300">
                                        <i class="fas fa-note-sticky text-primary mt-0.5 flex-shrink-0"></i>
                                        <p class="line-clamp-2"><?= htmlspecialchars($row['keterangan']) ?></p>
                                    </div>
                                    <?php else: ?>
                                    <div class="flex-1 text-gray-400 italic">
                                        <i class="fas fa-minus-circle mr-1"></i> Tidak ada keterangan
                                    </div>
                                    <?php endif; ?>
                                    <div class="flex items-center gap-2 text-gray-500 dark:text-gray-400 flex-shrink-0">
                                        <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                                            <i class="fas fa-user text-primary text-[10px]"></i>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($row['petugas']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Halaman <span class="font-semibold text-primary"><?= $page ?></span> 
                        dari <span class="font-semibold"><?= $total_pages ?></span>
                        <span class="text-xs">(<?= $total_records ?> data)</span>
                    </p>
                    <nav class="flex items-center gap-1">
                        <?php if($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&jenis=<?= urlencode($filter_jenis) ?>&cari=<?= urlencode($search) ?>" 
                           class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if($start_page > 1): ?>
                            <a href="?page=1&jenis=<?= urlencode($filter_jenis) ?>&cari=<?= urlencode($search) ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">1</a>
                            <?php if($start_page > 2): ?>
                                <span class="px-2 text-gray-400">...</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="?page=<?= $i ?>&jenis=<?= urlencode($filter_jenis) ?>&cari=<?= urlencode($search) ?>" 
                               class="px-3 py-2 text-sm rounded-lg transition-all <?= $i == $page ? 'bg-primary text-white shadow-md' : 'bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 hover:bg-primary hover:text-white hover:border-primary' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if($end_page < $total_pages): ?>
                            <?php if($end_page < $total_pages - 1): ?>
                                <span class="px-2 text-gray-400">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $total_pages ?>&jenis=<?= urlencode($filter_jenis) ?>&cari=<?= urlencode($search) ?>" 
                               class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all"><?= $total_pages ?></a>
                        <?php endif; ?>
                        
                        <?php if($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&jenis=<?= urlencode($filter_jenis) ?>&cari=<?= urlencode($search) ?>" 
                           class="px-3 py-2 text-sm bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-primary hover:text-white hover:border-primary transition-all">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if(mysqli_num_rows($result) == 0): ?>
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-12 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-primary/10 to-primary/20 flex items-center justify-center">
                        <i class="fas fa-route text-4xl text-primary"></i>
                    </div>
                    <div>
                        <p class="text-lg font-semibold text-gray-700 dark:text-gray-200">
                            <?= ($search || $filter_jenis) ? 'Tidak Ada Hasil' : 'Belum Ada Tracking' ?>
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            <?= ($search || $filter_jenis) ? 'Coba ubah filter atau kata kunci pencarian' : 'Mulai lacak perpindahan aset sekolah Anda' ?>
                        </p>
                    </div>
                    <?php if(!$search && !$filter_jenis && canCreate()): ?>
                    <div class="flex gap-2">
                        <button @click="openAddModal()" 
                                class="px-6 py-3 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm font-medium">
                            <i class="fas fa-plus-circle"></i>
                            Tracking Manual
                        </button>
                        <a href="peminjaman.php" 
                           class="px-6 py-3 bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm font-medium">
                            <i class="fas fa-hand-holding-heart"></i>
                            Buat Peminjaman
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
    </main>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function trackingApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        init() {
            const urlParams = new URLSearchParams(window.location.search);
            const action = urlParams.get('action');
            
            if (action === 'added') {
                this.showToast('Tracking berhasil ditambahkan!', 'success');
            } else if (action === 'deleted') {
                this.showToast('Tracking berhasil dihapus!', 'success');
            } else if (action === 'error') {
                this.showToast('Terjadi kesalahan!', 'error');
            }
        },
        
        filterBy(jenis) {
            if (!jenis) {
                window.location.href = 'tracking_aset.php';
            } else {
                window.location.href = `?jenis=${encodeURIComponent(jenis)}`;
            }
        },
        
        openAddModal() {
            Swal.fire({
                title: '<i class="fas fa-plus-circle text-primary mr-2"></i> Tambah Tracking Manual',
                html: `
                    <div class="text-left space-y-4 mt-4 max-h-[70vh] overflow-y-auto pr-2">
                        <div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                            <p class="text-xs text-amber-800 dark:text-amber-300">
                                <i class="fas fa-info-circle mr-1"></i>
                                <strong>Info:</strong> Tracking untuk Peminjaman/Pengembalian/Perpanjangan dibuat otomatis oleh modul Peminjaman. Gunakan form ini untuk tracking manual (Pindah Ruangan, Mutasi, Perbaikan).
                            </p>
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                <i class="fas fa-box text-primary"></i>
                                Pilih Aset <span class="text-red-500">*</span>
                            </label>
                            <select id="track-inventaris" class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm">
                                <option value="">-- Pilih Aset --</option>
                                <?php 
                                $asets = mysqli_query($conn, "SELECT i.id, i.nama_barang_108, r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id ORDER BY i.nama_barang_108 ASC");
                                while($a = mysqli_fetch_assoc($asets)): 
                                ?>
                                <option value="<?= $a['id'] ?>" data-ruangan="<?= htmlspecialchars(addslashes($a['nama_ruangan'] ?? '')) ?>">
                                    <?= htmlspecialchars($a['nama_barang_108']) ?> <?= $a['nama_ruangan'] ? '(' . htmlspecialchars($a['nama_ruangan']) . ')' : '' ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                    <i class="fas fa-calendar text-primary"></i>
                                    Tanggal <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="track-tanggal" value="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm">
                            </div>
                            <div>
                                <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                    <i class="fas fa-tag text-primary"></i>
                                    Jenis <span class="text-red-500">*</span>
                                </label>
                                <select id="track-jenis" onchange="updateLocationFields()" 
                                        class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm">
                                    <option value="">-- Pilih --</option>
                                    <option value="Pindah Ruangan">🔄 Pindah Ruangan</option>
                                    <option value="Mutasi">🔀 Mutasi</option>
                                    <option value="Perbaikan">🔧 Perbaikan</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="location-fields" class="space-y-3">
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                                <p class="text-xs text-blue-700 dark:text-blue-300">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Pilih jenis tracking untuk menampilkan field lokasi
                                </p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                <i class="fas fa-note-sticky text-primary"></i>
                                Keterangan
                            </label>
                            <textarea id="track-keterangan" rows="2" 
                                      class="w-full px-3 py-2.5 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 text-sm resize-none"
                                      placeholder="Catatan tambahan..." maxlength="250"></textarea>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#1a365d',
                cancelButtonColor: '#718096',
                confirmButtonText: '<i class="fas fa-save mr-1"></i> Simpan',
                cancelButtonText: 'Batal',
                width: '600px',
                didOpen: () => {
                    document.getElementById('track-inventaris').addEventListener('change', function() {
                        const selected = this.options[this.selectedIndex];
                        const currentRuangan = selected.getAttribute('data-ruangan');
                        const dariField = document.getElementById('track-dari');
                        if (dariField && currentRuangan) dariField.value = currentRuangan;
                    });
                },
                preConfirm: () => {
                    const inventaris_id = document.getElementById('track-inventaris').value;
                    const tanggal = document.getElementById('track-tanggal').value;
                    const jenis = document.getElementById('track-jenis').value;
                    const keterangan = document.getElementById('track-keterangan').value;
                    
                    if (!inventaris_id || !tanggal || !jenis) {
                        Swal.showValidationMessage('Lengkapi semua field yang wajib diisi');
                        return false;
                    }
                    
                    let dari = '', ke = '';
                    const dariField = document.getElementById('track-dari');
                    const dariSelect = document.getElementById('track-dari-select');
                    const keField = document.getElementById('track-ke');
                    const keSelect = document.getElementById('track-ke-select');
                    
                    if (dariField) dari = dariField.value;
                    else if (dariSelect) dari = dariSelect.value;
                    
                    if (keField) ke = keField.value;
                    else if (keSelect) ke = keSelect.value;
                    
                    return { inventaris_id, tanggal, jenis, dari, ke, keterangan };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="inventaris_id" value="${result.value.inventaris_id}">
                        <input type="hidden" name="tanggal_tracking" value="${result.value.tanggal}">
                        <input type="hidden" name="jenis_tracking" value="${result.value.jenis}">
                        <input type="hidden" name="dari_lokasi" value="${result.value.dari}">
                        <input type="hidden" name="ke_lokasi" value="${result.value.ke}">
                        <input type="hidden" name="keterangan" value="${result.value.keterangan}">
                        <input type="hidden" name="tambah_tracking" value="1">
                    `;
                    document.body.appendChild(form);
                    
                    Swal.fire({
                        title: 'Menyimpan...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    setTimeout(() => form.submit(), 400);
                }
            });
        },
        
        confirmDelete(id, nama) {
            Swal.fire({
                title: 'Hapus Tracking?',
                html: `
                    <p class="text-gray-600">Apakah Anda yakin ingin menghapus tracking untuk:</p>
                    <p class="font-bold text-primary mt-2">${nama}</p>
                    <p class="text-xs text-red-500 mt-3">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Riwayat tracking akan hilang permanen!
                    </p>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e53e3e',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    setTimeout(() => {
                        window.location.href = `?hapus=${id}`;
                    }, 400);
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

// Dynamic location fields (hanya untuk tracking manual)
function updateLocationFields() {
    const jenis = document.getElementById('track-jenis').value;
    const container = document.getElementById('location-fields');
    const inventarisSelect = document.getElementById('track-inventaris');
    const currentRuangan = inventarisSelect.options[inventarisSelect.selectedIndex]?.getAttribute('data-ruangan') || '';
    
    const ruanganOptions = `
        <option value="">-- Pilih Ruangan --</option>
        <?php 
        mysqli_data_seek($ruangan_list, 0);
        while($r = mysqli_fetch_assoc($ruangan_list)): 
        ?>
        <option value="<?= htmlspecialchars(addslashes($r['nama_ruangan'])) ?>">
            <?= htmlspecialchars($r['kode_ruangan']) ?> - <?= htmlspecialchars($r['nama_ruangan']) ?>
        </option>
        <?php endwhile; ?>
    `;
    
    let html = '';
    
    if (!jenis) {
        html = `<div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
            <p class="text-xs text-blue-700 dark:text-blue-300"><i class="fas fa-info-circle mr-1"></i> Pilih jenis tracking untuk menampilkan field lokasi</p>
        </div>`;
    } else if (jenis === 'Pindah Ruangan') {
        html = `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-sign-out-alt text-emerald-500 mr-1"></i> Dari Ruangan
                    </label>
                    <select id="track-dari-select" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        ${ruanganOptions}
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-sign-in-alt text-emerald-500 mr-1"></i> Ke Ruangan <span class="text-red-500">*</span>
                    </label>
                    <select id="track-ke-select" required class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                        ${ruanganOptions}
                    </select>
                </div>
            </div>
            <div class="p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded text-xs text-emerald-700 dark:text-emerald-300">
                <i class="fas fa-info-circle mr-1"></i> Lokasi aset akan otomatis diupdate ke ruangan tujuan
            </div>
        `;
        setTimeout(() => {
            const dariSelect = document.getElementById('track-dari-select');
            if (dariSelect && currentRuangan) {
                for (let opt of dariSelect.options) {
                    if (opt.value === currentRuangan) {
                        dariSelect.value = currentRuangan;
                        break;
                    }
                }
            }
        }, 100);
    } else if (jenis === 'Mutasi') {
        html = `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-sign-out-alt text-amber-500 mr-1"></i> Dari Lokasi
                    </label>
                    <input type="text" id="track-dari" value="${currentRuangan}" placeholder="Contoh: Gedung A"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-sign-in-alt text-amber-500 mr-1"></i> Ke Lokasi <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="track-ke" required placeholder="Contoh: Sekolah lain"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
            </div>
        `;
    } else if (jenis === 'Perbaikan') {
        html = `
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-location-dot text-red-500 mr-1"></i> Dari Lokasi
                    </label>
                    <input type="text" id="track-dari" value="${currentRuangan}" placeholder="Lokasi saat ini"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                        <i class="fas fa-tools text-red-500 mr-1"></i> Ke Tempat Perbaikan <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="track-ke" required placeholder="Contoh: Toko Service X"
                           class="w-full px-3 py-2 border border-gray-200 dark:border-gray-600 dark:bg-gray-700 rounded-lg text-sm">
                </div>
            </div>
        `;
    }
    
    container.innerHTML = html;
}

document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="cari"]');
        if (searchInput) searchInput.focus();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        const app = document.querySelector('[x-data]').__x;
        if (app) app.$data.openAddModal();
    }
});
</script>

</body>
</html>