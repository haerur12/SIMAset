<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ============================================
// ✅ PROSES: TAMBAH PEMINJAMAN
// ============================================
if(isset($_POST['tambah_peminjaman'])) {
    requireAccess('create', 'peminjaman.php');
    
    $inventaris_id = intval($_POST['inventaris_id']);
    $peminjam = mysqli_real_escape_string($conn, trim($_POST['peminjam']));
    $nip_peminjam = mysqli_real_escape_string($conn, trim($_POST['nip_peminjam'] ?? ''));
    $unit_kerja = mysqli_real_escape_string($conn, trim($_POST['unit_kerja'] ?? ''));
    $no_hp = mysqli_real_escape_string($conn, trim($_POST['no_hp'] ?? ''));
    $tanggal_pinjam = mysqli_real_escape_string($conn, $_POST['tanggal_pinjam']);
    $tanggal_kembali_rencana = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_rencana']);
    $jumlah = intval($_POST['jumlah'] ?? 1);
    $keperluan = mysqli_real_escape_string($conn, trim($_POST['keperluan'] ?? ''));
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan'] ?? ''));
    $kondisi_sebelum = mysqli_real_escape_string($conn, $_POST['kondisi_sebelum'] ?? 'Baik');
    $created_by = $_SESSION['user_id'] ?? null;
    
    // Validasi
    if(empty($peminjam) || empty($inventaris_id) || empty($tanggal_pinjam) || empty($tanggal_kembali_rencana)) {
        $_SESSION['flash_error'] = 'Field wajib harus diisi!';
        header("Location: peminjaman.php");
        exit;
    }
    
    if($tanggal_kembali_rencana < $tanggal_pinjam) {
        $_SESSION['flash_error'] = 'Tanggal kembali tidak boleh sebelum tanggal pinjam!';
        header("Location: peminjaman.php");
        exit;
    }
    
    // Cek stok tersedia
    $stok = mysqli_query($conn, "SELECT jumlah FROM inventaris WHERE id = $inventaris_id");
    $stok = $stok ? mysqli_fetch_assoc($stok) : ['jumlah' => 0];
    $dipinjam_aktif = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM peminjaman_aset WHERE inventaris_id = $inventaris_id AND status IN ('dipinjam', 'terlambat')")->fetch_assoc()['total'];
    $tersedia = $stok['jumlah'] - $dipinjam_aktif;
    
    if($jumlah > $tersedia) {
        $_SESSION['flash_error'] = "Stok tidak mencukupi! Tersedia: $tersedia unit";
        header("Location: peminjaman.php");
        exit;
    }
    
    // Ambil lokasi awal aset
    $lokasi_awal = mysqli_query($conn, "SELECT r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id WHERE i.id = $inventaris_id");
    $lokasi_awal = $lokasi_awal ? mysqli_fetch_assoc($lokasi_awal) : [];
    $dari_lokasi = $lokasi_awal['nama_ruangan'] ?? 'Gudang';

    $query = "INSERT INTO peminjaman_aset (
        inventaris_id, peminjam, nip_peminjam, unit_kerja, no_hp,
        tanggal_pinjam, tanggal_kembali_rencana, jumlah, keperluan,
        keterangan, kondisi_sebelum, created_by
    ) VALUES (
        $inventaris_id, '$peminjam', '$nip_peminjam', '$unit_kerja', '$no_hp',
        '$tanggal_pinjam', '$tanggal_kembali_rencana', $jumlah, '$keperluan',
        '$keterangan', '$kondisi_sebelum', $created_by
    )";
    
    if(mysqli_query($conn, $query)) {
        $peminjaman_id = mysqli_insert_id($conn);

        // ✅ AUTO TRACKING: Catat peminjaman di tracking_aset
        $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
        $ket_tracking = "Peminjaman oleh $peminjam" . ($unit_kerja ? " ($unit_kerja)" : "") . " - Jumlah: $jumlah unit" . ($keperluan ? " - Keperluan: $keperluan" : "");

        mysqli_query($conn, "INSERT INTO tracking_aset 
            (inventaris_id, peminjaman_id, tanggal_tracking, jenis_tracking, dari_lokasi, ke_lokasi, keterangan, petugas) 
            VALUES (
                $inventaris_id, 
                $peminjaman_id, 
                '$tanggal_pinjam', 
                'Peminjaman', 
                '" . mysqli_real_escape_string($conn, $dari_lokasi) . "', 
                '" . mysqli_real_escape_string($conn, $peminjam) . "', 
                '" . mysqli_real_escape_string($conn, $ket_tracking) . "', 
                '" . mysqli_real_escape_string($conn, $petugas) . "'
            )");

        $_SESSION['flash_success'] = 'Peminjaman berhasil dicatat & tercatat di tracking!';
    } else {
        $_SESSION['flash_error'] = 'Gagal menyimpan: ' . mysqli_error($conn);
    }
    header("Location: peminjaman.php");
    exit;
}

// ============================================
// ✅ PROSES: PENGEMBALIAN
// ============================================
if(isset($_POST['kembalikan_peminjaman'])) {
    requireAccess('update', 'peminjaman.php');
    
    $id = intval($_POST['id']);
    $tanggal_kembali_aktual = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_aktual'] ?? date('Y-m-d'));
    $kondisi_sesudah = mysqli_real_escape_string($conn, $_POST['kondisi_sesudah'] ?? 'Baik');
    $catatan_pengembalian = mysqli_real_escape_string($conn, trim($_POST['catatan_pengembalian'] ?? ''));
    $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
    
    // Ambil data peminjaman
    $dataRes = mysqli_query($conn, "SELECT p.*, i.nama_barang_108, r.nama_ruangan 
        FROM peminjaman_aset p 
        LEFT JOIN inventaris i ON p.inventaris_id = i.id 
        LEFT JOIN ruangan r ON i.ruangan_id = r.id 
        WHERE p.id = $id");
    $data = $dataRes ? mysqli_fetch_assoc($dataRes) : null;
    if(!$data) {
        $_SESSION['flash_error'] = 'Data peminjaman tidak ditemukan';
        header("Location: peminjaman.php");
        exit;
    }

    // Cek status
    $check_status = mysqli_query($conn, "SELECT status FROM peminjaman_aset WHERE id = $id");
    $check_status = $check_status ? mysqli_fetch_assoc($check_status) : ['status' => 'dipinjam'];
    $status = ($check_status['status'] === 'terlambat') ? 'terlambat' : 'dikembalikan';

    $query = "UPDATE peminjaman_aset SET 
        status = '$status',
        tanggal_kembali_aktual = '$tanggal_kembali_aktual',
        kondisi_sesudah = '$kondisi_sesudah',
        catatan_pengembalian = '$catatan_pengembalian',
        petugas_serah_terima = '$petugas'
        WHERE id = $id";

    if(mysqli_query($conn, $query)) {
        // ✅ AUTO TRACKING: Catat pengembalian di tracking_aset
        $ke_lokasi = $data['nama_ruangan'] ?? 'Gudang';
        $ket_tracking = "Pengembalian oleh {$data['peminjam']} - Kondisi: $kondisi_sesudah";
        if($catatan_pengembalian) $ket_tracking .= " - Catatan: $catatan_pengembalian";
        if($status === 'terlambat') $ket_tracking .= " [TERLAMBAT]";

        $petugas_esc = mysqli_real_escape_string($conn, $petugas);
        mysqli_query($conn, "INSERT INTO tracking_aset 
            (inventaris_id, peminjaman_id, tanggal_tracking, jenis_tracking, dari_lokasi, ke_lokasi, keterangan, petugas) 
            VALUES (
                {$data['inventaris_id']}, 
                $id, 
                '$tanggal_kembali_aktual', 
                'Pengembalian', 
                '" . mysqli_real_escape_string($conn, $data['peminjam']) . "', 
                '" . mysqli_real_escape_string($conn, $ke_lokasi) . "', 
                '" . mysqli_real_escape_string($conn, $ket_tracking) . "', 
                '$petugas_esc'
            )");

        $_SESSION['flash_success'] = 'Pengembalian berhasil dicatat & tercatat di tracking!';
    } else {
        $_SESSION['flash_error'] = 'Gagal menyimpan pengembalian';
    }
    header("Location: peminjaman.php?tab=" . ($_POST['tab'] ?? 'aktif'));
    exit;
}

// ============================================
// ✅ PROSES: PERPANJANG
// ============================================
if(isset($_POST['perpanjang_peminjaman'])) {
    requireAccess('update', 'peminjaman.php');
    
    $id = intval($_POST['id']);
    $tanggal_baru = mysqli_real_escape_string($conn, $_POST['tanggal_kembali_rencana']);
    
    // Ambil data lama
    $dataRes = mysqli_query($conn, "SELECT * FROM peminjaman_aset WHERE id = $id");
    $data = $dataRes ? mysqli_fetch_assoc($dataRes) : null;
    $tanggal_lama = $data['tanggal_kembali_rencana'] ?? null;

    $query = "UPDATE peminjaman_aset SET tanggal_kembali_rencana = '$tanggal_baru', status = 'dipinjam' WHERE id = $id";

    if(mysqli_query($conn, $query)) {
        // ✅ AUTO TRACKING: Catat perpanjangan di tracking_aset
        $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap'] ?? 'Admin');
        $ket_tracking = "Perpanjangan peminjaman oleh {$data['peminjam']} - Dari " . date('d/m/Y', strtotime($tanggal_lama)) . " menjadi " . date('d/m/Y', strtotime($tanggal_baru));

        mysqli_query($conn, "INSERT INTO tracking_aset 
            (inventaris_id, peminjaman_id, tanggal_tracking, jenis_tracking, dari_lokasi, ke_lokasi, keterangan, petugas) 
            VALUES (
                {$data['inventaris_id']}, 
                $id, 
                CURDATE(), 
                'Perpanjangan', 
                '" . mysqli_real_escape_string($conn, $data['peminjam']) . "', 
                '" . mysqli_real_escape_string($conn, $data['peminjam']) . "', 
                '" . mysqli_real_escape_string($conn, $ket_tracking) . "', 
                '$petugas'
            )");

        $_SESSION['flash_success'] = 'Peminjaman berhasil diperpanjang & tercatat di tracking!';
    }
    header("Location: peminjaman.php?tab=aktif");
    exit;
}

// ============================================
// ✅ FLASH MESSAGE
// ============================================
$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// ============================================
// ✅ STATISTIK
// ============================================
$stat_aktif = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'dipinjam'")->fetch_assoc()['total'];
$stat_terlambat = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'terlambat' OR (status = 'dipinjam' AND tanggal_kembali_rencana < CURDATE())")->fetch_assoc()['total'];
$stat_bulan_ini = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE MONTH(tanggal_pinjam) = MONTH(CURDATE()) AND YEAR(tanggal_pinjam) = YEAR(CURDATE())")->fetch_assoc()['total'];
$stat_dikembalikan = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset WHERE status = 'dikembalikan'")->fetch_assoc()['total'];
$stat_total = mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset")->fetch_assoc()['total'];

// ============================================
// ✅ FILTER & TAB
// ============================================
$tab = $_GET['tab'] ?? 'semua';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = [];
switch($tab) {
    case 'aktif':
        $where[] = "(p.status = 'dipinjam' AND p.tanggal_kembali_rencana >= CURDATE())";
        break;
    case 'terlambat':
        $where[] = "(p.status = 'terlambat' OR (p.status = 'dipinjam' AND p.tanggal_kembali_rencana < CURDATE()))";
        break;
    case 'dikembalikan':
        $where[] = "p.status = 'dikembalikan'";
        break;
}

if($search) {
    $where[] = "(p.peminjam LIKE '%$search%' OR i.nama_barang_108 LIKE '%$search%' OR p.unit_kerja LIKE '%$search%')";
}

$where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Query peminjaman
$query = "SELECT p.*, i.nama_barang_108, i.spesifikasi_nama_barang, i.jumlah as stok_total,
                 r.nama_ruangan, u.nama_lengkap as petugas_input
          FROM peminjaman_aset p
          LEFT JOIN inventaris i ON p.inventaris_id = i.id
          LEFT JOIN ruangan r ON i.ruangan_id = r.id
          LEFT JOIN users u ON p.created_by = u.id
          $where_sql
          ORDER BY 
            CASE p.status 
                WHEN 'terlambat' THEN 1
                WHEN 'dipinjam' THEN 2
                WHEN 'dikembalikan' THEN 3
            END,
            p.tanggal_pinjam DESC";
$result = mysqli_query($conn, $query);

// List aset untuk dropdown (hanya yang punya stok)
$aset_list = mysqli_query($conn, "SELECT i.id, i.nama_barang_108, i.jumlah, i.satuan,
    (i.jumlah - COALESCE((SELECT SUM(jumlah) FROM peminjaman_aset WHERE inventaris_id = i.id AND status IN ('dipinjam', 'terlambat')), 0)) as tersedia
    FROM inventaris i
    WHERE i.jumlah > 0
    ORDER BY i.nama_barang_108 ASC");
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Peminjaman - Inventaris SDN Curug 01</title>
    
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
                    fontFamily: { sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'] }
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
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        
        .tab-active {
            background: linear-gradient(135deg, #1a365d, #2c5282);
            color: white !important;
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
        }
        
        .row-terlambat {
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.08) 0%, transparent 100%) !important;
            border-left: 3px solid #ef4444 !important;
        }
        .row-dipinjam {
            border-left: 3px solid #3b82f6 !important;
        }
        .row-dikembalikan {
            border-left: 3px solid #10b981 !important;
            opacity: 0.85;
        }
        
        .pulse-dot {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Modal animation */
        .modal-enter { animation: modalEnter 0.3s ease-out; }
        @keyframes modalEnter {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="peminjamanApp()">
    
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
                        <span class="text-primary font-semibold">Peminjaman Aset</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-hand-holding-heart"></i>
                        <span>Manajemen Peminjaman Aset</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                
                <?php if(canCreate()): ?>
                <button @click="openFormPeminjaman()" 
                        class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gradient-to-r from-primary to-primary-light hover:from-primary-dark hover:to-primary text-white rounded-lg shadow-md hover:shadow-lg transition-all">
                    <i class="fas fa-plus-circle"></i>
                    <span class="text-sm font-medium">Peminjaman Baru</span>
                </button>
                <?php endif; ?>
            </div>
        </header>
        
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Flash Messages -->
            <?php if($flash_success): ?>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border-l-4 border-emerald-500 rounded-lg p-4 flex items-center gap-3 animate-slide-in-left">
                <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                <span class="text-emerald-800 dark:text-emerald-300 font-medium"><?= htmlspecialchars($flash_success) ?></span>
            </div>
            <?php endif; ?>
            
            <?php if($flash_error): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg p-4 flex items-center gap-3 animate-slide-in-left">
                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                <span class="text-red-800 dark:text-red-300 font-medium"><?= htmlspecialchars($flash_error) ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <?php 
                $stats = [
                    ['Total Peminjaman', $stat_total, 'fa-hand-holding', 'from-blue-500 to-blue-600', 'Semua waktu'],
                    ['Sedang Dipinjam', $stat_aktif, 'fa-clock', 'from-sky-500 to-sky-600', 'Aktif sekarang'],
                    ['Terlambat', $stat_terlambat, 'fa-exclamation-triangle', 'from-red-500 to-red-600', 'Perlu perhatian'],
                    ['Bulan Ini', $stat_bulan_ini, 'fa-calendar', 'from-purple-500 to-purple-600', 'Peminjaman baru'],
                    ['Dikembalikan', $stat_dikembalikan, 'fa-check-circle', 'from-emerald-500 to-emerald-600', 'Selesai'],
                ];
                foreach($stats as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-4 lg:p-5">
                        <div class="flex items-start justify-between mb-2">
                            <div class="p-2.5 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-base"></i>
                            </div>
                        </div>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-2xl lg:text-3xl font-bold text-gray-800 dark:text-white"><?= number_format($stat[1]) ?></h3>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1"><?= $stat[4] ?></p>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Main Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <!-- Tabs & Search -->
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col gap-4">
                        
                        <!-- Tabs -->
                        <div class="flex flex-wrap gap-2">
                            <a href="?tab=semua<?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 <?= $tab === 'semua' ? 'tab-active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' ?>">
                                <i class="fas fa-list"></i>
                                <span>Semua</span>
                                <span class="ml-1 px-2 py-0.5 bg-white/20 rounded-full text-[10px]"><?= $stat_total ?></span>
                            </a>
                            <a href="?tab=aktif<?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 <?= $tab === 'aktif' ? 'tab-active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' ?>">
                                <i class="fas fa-clock"></i>
                                <span>Aktif</span>
                                <span class="ml-1 px-2 py-0.5 bg-sky-100 text-sky-700 rounded-full text-[10px]"><?= $stat_aktif ?></span>
                            </a>
                            <a href="?tab=terlambat<?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 <?= $tab === 'terlambat' ? 'tab-active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' ?>">
                                <i class="fas fa-exclamation-triangle"></i>
                                <span>Terlambat</span>
                                <span class="ml-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full text-[10px]"><?= $stat_terlambat ?></span>
                            </a>
                            <a href="?tab=dikembalikan<?= $search ? '&search='.urlencode($search) : '' ?>" 
                               class="px-4 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2 <?= $tab === 'dikembalikan' ? 'tab-active' : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' ?>">
                                <i class="fas fa-check-circle"></i>
                                <span>Dikembalikan</span>
                                <span class="ml-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-[10px]"><?= $stat_dikembalikan ?></span>
                            </a>
                        </div>
                        
                        <!-- Search -->
                        <form method="GET" class="flex gap-2 w-full lg:w-auto">
                            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
                            <div class="relative flex-1 lg:w-96">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" 
                                       placeholder="Cari peminjam, nama barang, atau unit kerja..." 
                                       value="<?= htmlspecialchars($search) ?>"
                                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-all text-sm">
                            </div>
                            <button type="submit" class="px-5 py-2.5 bg-primary hover:bg-primary-dark text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center gap-2 text-sm font-medium">
                                <i class="fas fa-search"></i>
                                <span class="hidden sm:inline">Cari</span>
                            </button>
                            <?php if($search): ?>
                            <a href="?tab=<?= $tab ?>" class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center gap-2 text-sm font-medium">
                                <i class="fas fa-times"></i>
                            </a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Peminjam / Aset</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Unit Kerja</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Petugas</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if(mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-inbox text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Tidak ada data peminjaman</p>
                                        <?php if(canCreate()): ?>
                                        <button @click="openFormPeminjaman()" class="mt-2 px-4 py-2 bg-primary hover:bg-primary-dark text-white rounded-lg text-sm transition-all">
                                            <i class="fas fa-plus mr-1"></i> Catat Peminjaman Baru
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php else:
                                while($row = mysqli_fetch_assoc($result)): 
                                    // Cek status terlambat real-time
                                    $is_terlambat = ($row['status'] === 'dipinjam' && $row['tanggal_kembali_rencana'] < date('Y-m-d'));
                                    $actual_status = $is_terlambat ? 'terlambat' : $row['status'];
                                    
                                    $row_class = '';
                                    if($actual_status === 'terlambat') $row_class = 'row-terlambat';
                                    elseif($actual_status === 'dipinjam') $row_class = 'row-dipinjam';
                                    else $row_class = 'row-dikembalikan';
                                    
                                    $status_badge = [
                                        'dipinjam' => ['bg-blue-100 text-blue-700 border-blue-200', 'fa-clock', 'Dipinjam'],
                                        'terlambat' => ['bg-red-100 text-red-700 border-red-200 pulse-dot', 'fa-exclamation-triangle', 'Terlambat'],
                                        'dikembalikan' => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'fa-check-circle', 'Dikembalikan'],
                                    ];
                                    $badge = $status_badge[$actual_status];
                                    
                                    $hari_sisa = 0;
                                    if($actual_status === 'dipinjam') {
                                        $hari_sisa = floor((strtotime($row['tanggal_kembali_rencana']) - time()) / 86400);
                                    } elseif($actual_status === 'terlambat') {
                                        $hari_sisa = floor((time() - strtotime($row['tanggal_kembali_rencana'])) / 86400);
                                    }
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors <?= $row_class ?>">
                                <td class="px-4 py-4">
                                    <div class="flex items-start gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-primary/10 to-primary/20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-user-tie text-primary"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-800 dark:text-white"><?= htmlspecialchars($row['peminjam']) ?></div>
                                            <?php if($row['nip_peminjam']): ?>
                                            <div class="text-[10px] text-gray-500 font-mono">NIP: <?= htmlspecialchars($row['nip_peminjam']) ?></div>
                                            <?php endif; ?>
                                            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1 flex items-center gap-1">
                                                <i class="fas fa-box text-[10px]"></i>
                                                <span class="truncate max-w-[200px]"><?= htmlspecialchars($row['nama_barang_108']) ?></span>
                                                <span class="text-primary font-semibold">×<?= $row['jumlah'] ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= htmlspecialchars($row['unit_kerja'] ?: '-') ?></div>
                                        <?php if($row['no_hp']): ?>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <i class="fas fa-phone text-[10px]"></i> <?= htmlspecialchars($row['no_hp']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <div class="text-xs">
                                        <div class="flex items-center justify-center gap-1 text-emerald-600 dark:text-emerald-400">
                                            <i class="fas fa-arrow-right text-[8px]"></i>
                                            <span><?= date('d/m/y', strtotime($row['tanggal_pinjam'])) ?></span>
                                        </div>
                                        <div class="flex items-center justify-center gap-1 <?= $actual_status === 'terlambat' ? 'text-red-600' : 'text-red-500' ?>">
                                            <i class="fas fa-arrow-left text-[8px]"></i>
                                            <span class="font-semibold"><?= date('d/m/y', strtotime($row['tanggal_kembali_rencana'])) ?></span>
                                        </div>
                                        <?php if($actual_status === 'dipinjam' && $hari_sisa > 0): ?>
                                        <div class="text-[10px] text-amber-600 mt-0.5">
                                            <i class="fas fa-hourglass-half"></i> <?= $hari_sisa ?> hari lagi
                                        </div>
                                        <?php elseif($actual_status === 'terlambat'): ?>
                                        <div class="text-[10px] text-red-600 font-bold mt-0.5">
                                            <i class="fas fa-exclamation-circle"></i> +<?= $hari_sisa ?> hari
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold border <?= $badge[0] ?>">
                                        <i class="fas <?= $badge[1] ?> text-[10px]"></i>
                                        <?= $badge[2] ?>
                                    </span>
                                    <?php if($row['kondisi_sebelum']): ?>
                                    <div class="text-[10px] text-gray-500 mt-1">
                                        <i class="fas fa-heart-pulse"></i> <?= $row['kondisi_sebelum'] ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-xs text-gray-700 dark:text-gray-300">
                                        <div class="font-medium"><?= htmlspecialchars($row['petugas_input'] ?? '-') ?></div>
                                        <div class="text-[10px] text-gray-500"><?= date('d/m/y H:i', strtotime($row['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <button onclick="showDetail(<?= $row['id'] ?>)" 
                                                class="p-2 bg-blue-100 hover:bg-blue-500 text-blue-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                                title="Detail">
                                            <i class="fas fa-eye text-xs"></i>
                                        </button>
                                        
                                        <?php if(canUpdate() && ($actual_status === 'dipinjam' || $actual_status === 'terlambat')): ?>
                                        <button onclick="openPerpanjang(<?= $row['id'] ?>, '<?= $row['tanggal_kembali_rencana'] ?>')" 
                                                class="p-2 bg-amber-100 hover:bg-amber-500 text-amber-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                                title="Perpanjang">
                                            <i class="fas fa-calendar-plus text-xs"></i>
                                        </button>
                                        <button onclick="openPengembalian(<?= $row['id'] ?>)" 
                                                class="p-2 bg-emerald-100 hover:bg-emerald-500 text-emerald-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                                title="Kembalikan">
                                            <i class="fas fa-undo text-xs"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <a href="cetak_surat_peminjaman.php?id=<?= $row['id'] ?>" target="_blank"
                                           class="p-2 bg-purple-100 hover:bg-purple-500 text-purple-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                           title="Cetak Surat">
                                            <i class="fas fa-print text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Footer Info -->
                <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30 flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
                    <span><i class="fas fa-table-cells mr-1"></i> <?= mysqli_num_rows($result) ?> data ditampilkan</span>
                    <span><i class="fas fa-info-circle mr-1"></i> Klik icon untuk aksi</span>
                </div>
            </div>
            
        </div>
    </main>
</div>

<!-- ============================================ -->
<!-- ✅ MODAL: FORM PEMINJAMAN BARU -->
<!-- ============================================ -->
<div id="modalPeminjaman" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col modal-enter">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary to-primary-light text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-hand-holding-heart text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Catat Peminjaman Baru</h3>
                    <p class="text-xs text-white/80">Isi formulir peminjaman aset</p>
                </div>
            </div>
            <button onclick="closeModal('modalPeminjaman')" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="flex-1 overflow-y-auto">
            <div class="p-5 space-y-5">
                
                <!-- Pilih Aset -->
                <div>
                    <label class="flex items-center gap-2 text-sm font-bold text-gray-800 dark:text-white mb-2">
                        <i class="fas fa-box text-primary"></i>
                        Pilih Aset yang Dipinjam <span class="text-red-500">*</span>
                    </label>
                    <select name="inventaris_id" required class="w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                        <option value="">-- Pilih Aset --</option>
                        <?php while($aset = mysqli_fetch_assoc($aset_list)): ?>
                        <option value="<?= $aset['id'] ?>" data-stok="<?= $aset['tersedia'] ?>">
                            <?= htmlspecialchars($aset['nama_barang_108']) ?> (Tersedia: <?= $aset['tersedia'] ?> <?= $aset['satuan'] ?>)
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Data Peminjam -->
                <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
                    <p class="text-xs font-bold text-blue-700 dark:text-blue-300 mb-3 flex items-center gap-1">
                        <i class="fas fa-user-tie"></i> Data Peminjam
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Nama Peminjam <span class="text-red-500">*</span></label>
                            <input type="text" name="peminjam" required placeholder="Nama lengkap" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">NIP</label>
                            <input type="text" name="nip_peminjam" placeholder="Nomor Induk Pegawai" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm font-mono">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Unit Kerja</label>
                            <input type="text" name="unit_kerja" placeholder="Contoh: Kelas 1A / TU" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">No. HP</label>
                            <input type="text" name="no_hp" placeholder="08xxxxxxxxxx" class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                        </div>
                    </div>
                </div>
                
                <!-- Periode & Jumlah -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Tanggal Pinjam <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_pinjam" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Tanggal Kembali <span class="text-red-500">*</span></label>
                        <input type="date" name="tanggal_kembali_rencana" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Jumlah <span class="text-red-500">*</span></label>
                        <input type="number" name="jumlah" required min="1" value="1" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                    </div>
                </div>
                
                <!-- Kondisi & Keperluan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Kondisi Saat Dipinjam</label>
                        <select name="kondisi_sebelum" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                            <option value="Baik">✅ Baik</option>
                            <option value="Rusak Ringan">⚠️ Rusak Ringan</option>
                            <option value="Rusak Berat">❌ Rusak Berat</option>
                            <option value="Dalam Perbaikan">🔧 Dalam Perbaikan</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Keperluan</label>
                        <input type="text" name="keperluan" placeholder="Contoh: Untuk kegiatan kelas" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
                    </div>
                </div>
                
                <div>
                    <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Keterangan Tambahan</label>
                    <textarea name="keterangan" rows="2" placeholder="Catatan tambahan..." class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm resize-none"></textarea>
                </div>
                
            </div>
            
            <div class="p-4 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex items-center justify-end gap-2">
                <button type="button" onclick="closeModal('modalPeminjaman')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all">
                    Batal
                </button>
                <button type="submit" name="tambah_peminjaman" class="px-5 py-2 bg-gradient-to-r from-primary to-primary-light hover:from-primary-dark hover:to-primary text-white rounded-lg shadow-md text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-save"></i>
                    Simpan Peminjaman
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- ✅ MODAL: PENGEMBALIAN -->
<!-- ============================================ -->
<div id="modalPengembalian" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden modal-enter">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-undo text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Pengembalian Aset</h3>
                    <p class="text-xs text-white/80">Catat pengembalian aset</p>
                </div>
            </div>
            <button onclick="closeModal('modalPengembalian')" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="id" id="pengembalian_id">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
            
            <div>
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Tanggal Dikembalikan <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_kembali_aktual" required value="<?= date('Y-m-d') ?>" class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
            </div>
            
            <div>
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-2 block">Kondisi Saat Dikembalikan <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_sesudah" value="Baik" required class="hidden peer">
                        <div class="p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg peer-checked:border-emerald-500 peer-checked:bg-emerald-50 dark:peer-checked:bg-emerald-900/20 transition-all text-center">
                            <i class="fas fa-check-circle text-emerald-500 text-xl mb-1"></i>
                            <div class="text-xs font-semibold">Baik</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_sesudah" value="Rusak Ringan" class="hidden peer">
                        <div class="p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg peer-checked:border-amber-500 peer-checked:bg-amber-50 dark:peer-checked:bg-amber-900/20 transition-all text-center">
                            <i class="fas fa-exclamation-triangle text-amber-500 text-xl mb-1"></i>
                            <div class="text-xs font-semibold">Rusak Ringan</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_sesudah" value="Rusak Berat" class="hidden peer">
                        <div class="p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg peer-checked:border-red-500 peer-checked:bg-red-50 dark:peer-checked:bg-red-900/20 transition-all text-center">
                            <i class="fas fa-times-circle text-red-500 text-xl mb-1"></i>
                            <div class="text-xs font-semibold">Rusak Berat</div>
                        </div>
                    </label>
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_sesudah" value="Dalam Perbaikan" class="hidden peer">
                        <div class="p-3 border-2 border-gray-200 dark:border-gray-600 rounded-lg peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20 transition-all text-center">
                            <i class="fas fa-wrench text-blue-500 text-xl mb-1"></i>
                            <div class="text-xs font-semibold">Perbaikan</div>
                        </div>
                    </label>
                </div>
            </div>
            
            <div>
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Catatan Pengembalian</label>
                <textarea name="catatan_pengembalian" rows="2" placeholder="Catatan kondisi aset saat dikembalikan..." class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm resize-none"></textarea>
            </div>
            
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('modalPengembalian')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all">
                    Batal
                </button>
                <button type="submit" name="kembalikan_peminjaman" class="px-5 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 text-white rounded-lg shadow-md text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-check"></i>
                    Konfirmasi Pengembalian
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- ✅ MODAL: PERPANJANG -->
<!-- ============================================ -->
<div id="modalPerpanjang" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden modal-enter">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-amber-500 to-amber-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calendar-plus text-xl"></i>
                </div>
                <div>
                    <h3 class="text-lg font-bold">Perpanjang Peminjaman</h3>
                    <p class="text-xs text-white/80">Perpanjang tanggal pengembalian</p>
                </div>
            </div>
            <button onclick="closeModal('modalPerpanjang')" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="POST" class="p-5 space-y-4">
            <input type="hidden" name="id" id="perpanjang_id">
            
            <div>
                <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1 block">Tanggal Kembali Baru <span class="text-red-500">*</span></label>
                <input type="date" name="tanggal_kembali_rencana" id="perpanjang_tanggal" required class="w-full px-3 py-2 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary text-sm">
            </div>
            
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" onclick="closeModal('modalPerpanjang')" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all">
                    Batal
                </button>
                <button type="submit" name="perpanjang_peminjaman" class="px-5 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-lg shadow-md text-sm font-semibold transition-all flex items-center gap-2">
                    <i class="fas fa-calendar-check"></i>
                    Perpanjang
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- ✅ MODAL: DETAIL -->
<!-- ============================================ -->
<div id="modalDetail" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-[1000] hidden items-center justify-center p-4" style="display: none;">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col modal-enter">
        <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-500 to-blue-600 text-white flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <i class="fas fa-info-circle text-xl"></i>
                </div>
                <h3 class="text-lg font-bold">Detail Peminjaman</h3>
            </div>
            <button onclick="closeModal('modalDetail')" class="w-8 h-8 rounded-full bg-white/20 hover:bg-white/30 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div id="detailContent" class="flex-1 overflow-y-auto p-5">
            <!-- Content akan diisi via AJAX -->
        </div>
    </div>
</div>

<script>
function peminjamanApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        },
        
        confirmLogout() {
            Swal.fire({
                title: 'Logout?',
                text: 'Apakah Anda yakin ingin keluar?',
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

// Modal functions
function openModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'flex';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.style.display = 'none';
    modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function openFormPeminjaman() {
    openModal('modalPeminjaman');
}

function openPengembalian(id) {
    document.getElementById('pengembalian_id').value = id;
    openModal('modalPengembalian');
}

function openPerpanjang(id, tanggalLama) {
    document.getElementById('perpanjang_id').value = id;
    document.getElementById('perpanjang_tanggal').value = tanggalLama;
    openModal('modalPerpanjang');
}

function showDetail(id) {
    // Fetch detail via AJAX
    fetch(`api_peminjaman_detail.php?id=${id}`)
        .then(r => r.text())
        .then(html => {
            document.getElementById('detailContent').innerHTML = html;
            openModal('modalDetail');
        })
        .catch(err => {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Gagal memuat detail' });
        });
}

// Close modal on ESC
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        ['modalPeminjaman', 'modalPengembalian', 'modalPerpanjang', 'modalDetail'].forEach(id => {
            const modal = document.getElementById(id);
            if (modal && modal.style.display === 'flex') closeModal(id);
        });
    }
});

// Close modal on backdrop click
['modalPeminjaman', 'modalPengembalian', 'modalPerpanjang', 'modalDetail'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', function(e) {
        if (e.target === this) closeModal(id);
    });
});
</script>

</body>
</html>