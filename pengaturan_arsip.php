<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Hanya admin yang bisa akses
if(!isAdmin()) {
    $_SESSION['flash_error'] = 'Akses ditolak! Hanya admin yang dapat mengubah pengaturan.';
    header("Location: dashboard.php");
    exit;
}

// ============================================
// ✅ PROSES: UPDATE SETTING
// ============================================
if(isset($_POST['update_tanggal'])) {
    $tanggal = intval($_POST['tanggal_arsip']);
    $bulan = intval($_POST['bulan_arsip']);
    $tahun = intval($_POST['tahun_arsip']);
    
    // Validasi
    if($tanggal < 1 || $tanggal > 31) {
        $_SESSION['flash_error'] = 'Tanggal harus antara 1-31!';
    } elseif($bulan < 1 || $bulan > 12) {
        $_SESSION['flash_error'] = 'Bulan harus antara 1-12!';
    } elseif($tahun < 2020 || $tahun > date('Y') + 5) {
        $_SESSION['flash_error'] = 'Tahun tidak valid!';
    } else {
        // Simpan ke database
        $tanggal_esc = mysqli_real_escape_string($conn, $tanggal);
        $bulan_esc = mysqli_real_escape_string($conn, $bulan);
        $tahun_esc = mysqli_real_escape_string($conn, $tahun);
        
        // Simpan sebagai JSON
        $setting_value = json_encode([
            'tanggal' => $tanggal,
            'bulan' => $bulan,
            'tahun' => $tahun
        ]);
        
        $query = mysqli_query($conn, "INSERT INTO pengaturan_sistem (key_name, value, tanggal_arsip, bulan_arsip, tahun_arsip, keterangan) 
            VALUES ('tanggal_arsip_bulanan', '$setting_value', $tanggal_esc, $bulan_esc, $tahun_esc, 'Tanggal pembuatan arsip bulanan')
            ON DUPLICATE KEY UPDATE 
                value = '$setting_value',
                tanggal_arsip = $tanggal_esc,
                bulan_arsip = $bulan_esc,
                tahun_arsip = $tahun_esc,
                updated_at = NOW()");
        
        if($query) {
            $_SESSION['flash_success'] = "Tanggal arsip bulanan berhasil diubah menjadi setiap <strong>tanggal $tanggal bulan " . nama_bulan_indo($bulan) . " $tahun</strong>!";
        } else {
            $_SESSION['flash_error'] = 'Gagal menyimpan pengaturan: ' . mysqli_error($conn);
        }
    }
    header("Location: pengaturan_arsip.php");
    exit;
}

// ============================================
// ✅ PROSES: RESET NOTIFIKASI BULAN INI
// ============================================
if(isset($_GET['reset_notif'])) {
    $bulan = intval($_GET['bulan'] ?? date('n'));
    $tahun = intval($_GET['tahun'] ?? date('Y'));
    
    mysqli_query($conn, "DELETE FROM arsip_notifikasi_log WHERE bulan = $bulan AND tahun = $tahun");
    $_SESSION['flash_success'] = "Notifikasi untuk " . nama_bulan_indo($bulan) . " $tahun berhasil direset!";
    header("Location: pengaturan_arsip.php");
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Ambil setting saat ini
$setting_raw = get_setting('tanggal_arsip_bulanan', null);
$setting = $setting_raw ? json_decode($setting_raw, true) : null;

// Jika setting lama (belum ada JSON), parse manual
if(!$setting && $setting_raw) {
    $setting = [
        'tanggal' => intval($setting_raw),
        'bulan' => date('n'),
        'tahun' => date('Y')
    ];
}

// Default jika belum ada setting
if(!$setting) {
    $setting = [
        'tanggal' => 27,
        'bulan' => date('n'),
        'tahun' => date('Y')
    ];
}

$tanggal_setting = $setting['tanggal'] ?? 27;
$bulan_setting = $setting['bulan'] ?? date('n');
$tahun_setting = $setting['tahun'] ?? date('Y');

// Ambil log notifikasi bulan ini
$bulan_sekarang = date('n');
$tahun_sekarang = date('Y');
$notif_log = mysqli_query($conn, "SELECT * FROM arsip_notifikasi_log 
    WHERE bulan = $bulan_sekarang AND tahun = $tahun_sekarang LIMIT 1");
$notif_log = $notif_log ? mysqli_fetch_assoc($notif_log) : null;

// Ambil semua log notifikasi
$all_notif_log = mysqli_query($conn, "SELECT * FROM arsip_notifikasi_log ORDER BY tahun DESC, bulan DESC LIMIT 12");

// Daftar bulan
$bulan_list = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Arsip Bulanan - Inventaris SDN Curug 01</title>
    
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
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen">
    
    <div x-show="sidebarOpen" x-transition @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/50 z-40 lg:hidden" style="display: none;"></div>

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
                        <span class="text-primary font-semibold">Pengaturan Arsip</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-cog"></i>
                        <span>Pengaturan Arsip Bulanan</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <a href="dashboard.php" class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all">
                    <i class="fas fa-arrow-left"></i>
                    <span class="text-sm font-medium">Kembali</span>
                </a>
            </div>
        </header>
        
        <div class="flex-1 p-4 lg:p-8 space-y-6">
            
            <!-- Flash Messages -->
            <?php if($flash_success): ?>
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border-l-4 border-emerald-500 rounded-lg p-4 flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-500 text-xl"></i>
                <span class="text-emerald-800 dark:text-emerald-300 font-medium"><?= $flash_success ?></span>
            </div>
            <?php endif; ?>
            
            <?php if($flash_error): ?>
            <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg p-4 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                <span class="text-red-800 dark:text-red-300 font-medium"><?= $flash_error ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Info Card -->
            <div class="bg-gradient-to-br from-blue-500 to-indigo-600 rounded-2xl shadow-xl p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="relative flex items-start gap-4">
                    <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                        <i class="fas fa-info-circle text-2xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl font-bold mb-2">Tentang Arsip Bulanan Manual</h3>
                        <ul class="text-sm text-white/90 space-y-1">
                            <li>• Arsip dibuat <strong>manual</strong> oleh admin (tanpa cron job)</li>
                            <li>• Sistem akan memberikan <strong>notifikasi besar</strong> saat tanggal yang disetting tiba</li>
                            <li>• Tanggal setting saat ini: <strong class="text-yellow-300">Tanggal <?= $tanggal_setting ?> <?= nama_bulan_indo($bulan_setting) ?> <?= $tahun_setting ?></strong></li>
                            <li>• Setelah arsip dibuat, notifikasi akan hilang otomatis</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Setting Tanggal -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <i class="fas fa-calendar-alt text-primary text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Setting Tanggal Arsip</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Pilih tanggal, bulan, dan tahun untuk notifikasi arsip</p>
                        </div>
                    </div>
                </div>
                
                <form method="POST" class="p-5 lg:p-6">
                    <div class="mb-4">
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-200 mb-3 block">
                            <i class="fas fa-calendar-day text-primary mr-1"></i>
                            Pilih Tanggal Notifikasi Arsip:
                        </label>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Tanggal -->
                            <div>
                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2 block">
                                    <i class="fas fa-calendar-day text-primary mr-1"></i> Tanggal
                                </label>
                                <select name="tanggal_arsip" required 
                                        class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-semibold">
                                    <?php for($i = 1; $i <= 31; $i++): ?>
                                    <option value="<?= $i ?>" <?= $tanggal_setting == $i ? 'selected' : '' ?>>
                                        Tanggal <?= $i ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <!-- Bulan -->
                            <div>
                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2 block">
                                    <i class="fas fa-calendar text-primary mr-1"></i> Bulan
                                </label>
                                <select name="bulan_arsip" required 
                                        class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-semibold">
                                    <?php foreach($bulan_list as $num => $nama): ?>
                                    <option value="<?= $num ?>" <?= $bulan_setting == $num ? 'selected' : '' ?>>
                                        <?= $nama ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Tahun -->
                            <div>
                                <label class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-2 block">
                                    <i class="fas fa-calendar-alt text-primary mr-1"></i> Tahun
                                </label>
                                <select name="tahun_arsip" required 
                                        class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-semibold">
                                    <?php for($y = date('Y'); $y <= date('Y') + 5; $y++): ?>
                                    <option value="<?= $y ?>" <?= $tahun_setting == $y ? 'selected' : '' ?>>
                                        Tahun <?= $y ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview -->
                    <div class="mb-6 p-4 bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-xs font-bold text-amber-800 dark:text-amber-300 mb-2 flex items-center gap-1">
                            <i class="fas fa-eye"></i> Preview Notifikasi
                        </p>
                        <p class="text-sm text-amber-900 dark:text-amber-200">
                            Notifikasi akan muncul setiap <strong class="text-lg text-red-600">tanggal <?= $tanggal_setting ?> <?= nama_bulan_indo($bulan_setting) ?> <?= $tahun_setting ?></strong> dan bulan-bulan berikutnya.
                        </p>
                        <p class="text-xs text-amber-700 dark:text-amber-400 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Contoh: Notifikasi akan muncul pada tanggal <?= $tanggal_setting ?> <?= nama_bulan_indo($bulan_setting) ?> <?= $tahun_setting ?>, 
                            <?= $tanggal_setting ?> <?= nama_bulan_indo($bulan_setting + 1 > 12 ? 1 : $bulan_setting + 1) ?> <?= $tahun_setting + ($bulan_setting + 1 > 12 ? 1 : 0) ?>, dst.
                        </p>
                    </div>
                    
                    <div class="flex items-center justify-end gap-2">
                        <button type="reset" 
                                class="px-4 py-2 bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg text-sm font-semibold transition-all">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                        <button type="submit" name="update_tanggal" 
                                class="px-6 py-2 bg-gradient-to-r from-primary to-primary-light hover:from-primary-dark hover:to-primary text-white rounded-lg shadow-md hover:shadow-lg transition-all font-semibold flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Status Notifikasi Bulan Ini -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <i class="fas fa-bell text-primary text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Status Notifikasi Bulan Ini</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400"><?= nama_bulan_indo($bulan_sekarang) ?> <?= $tahun_sekarang ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="p-5 lg:p-6">
                    <?php if($notif_log): 
                        $status_info = [
                            'pending' => ['bg-amber-100 text-amber-700 border-amber-200', 'fa-clock', 'Menunggu', 'Notifikasi akan muncul saat tanggal setting tiba'],
                            'dismissed' => ['bg-blue-100 text-blue-700 border-blue-200', 'fa-eye-slash', 'Ditunda', 'Notifikasi ditunda untuk bulan ini'],
                            'completed' => ['bg-emerald-100 text-emerald-700 border-emerald-200', 'fa-check-circle', 'Selesai', 'Arsip sudah dibuat untuk bulan ini'],
                        ];
                        $info = $status_info[$notif_log['status']] ?? $status_info['pending'];
                    ?>
                    <div class="flex items-center gap-4 p-4 rounded-lg border-2 <?= $info[0] ?>">
                        <div class="w-14 h-14 rounded-full bg-white flex items-center justify-center">
                            <i class="fas <?= $info[1] ?> text-2xl"></i>
                        </div>
                        <div class="flex-1">
                            <p class="font-bold text-lg"><?= $info[2] ?></p>
                            <p class="text-sm opacity-80"><?= $info[3] ?></p>
                            <?php if($notif_log['dismissed_at']): ?>
                            <p class="text-xs mt-1">Ditunda pada: <?= date('d/m/Y H:i', strtotime($notif_log['dismissed_at'])) ?></p>
                            <?php elseif($notif_log['completed_at']): ?>
                            <p class="text-xs mt-1">Diselesaikan pada: <?= date('d/m/Y H:i', strtotime($notif_log['completed_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php if($notif_log['status'] !== 'pending'): ?>
                            <button onclick="confirmResetNotif(<?= $bulan_sekarang ?>, <?= $tahun_sekarang ?>)" 
                                    class="px-4 py-2 bg-white hover:bg-gray-100 text-gray-700 rounded-lg shadow-sm transition-all text-sm font-semibold flex items-center gap-2">
                                <i class="fas fa-redo"></i>
                                <span>Reset</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-bell-slash text-5xl text-gray-300 dark:text-gray-600 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada log notifikasi untuk bulan ini</p>
                        <p class="text-sm text-gray-400 mt-1">Log akan dibuat otomatis saat tanggal setting tiba</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Riwayat Log -->
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <i class="fas fa-history text-primary text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Riwayat Notifikasi (12 Bulan Terakhir)</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Log aktivitas notifikasi arsip bulanan</p>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wider">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Dibuat</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Ditunda</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Selesai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if(mysqli_num_rows($all_notif_log) == 0): ?>
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                    <i class="fas fa-inbox text-3xl mb-2"></i>
                                    <p>Belum ada riwayat</p>
                                </td>
                            </tr>
                            <?php else:
                                while($log = mysqli_fetch_assoc($all_notif_log)):
                                    $status_badge = [
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        'dismissed' => 'bg-blue-100 text-blue-700',
                                        'completed' => 'bg-emerald-100 text-emerald-700',
                                    ];
                                    $status_label = [
                                        'pending' => 'Menunggu',
                                        'dismissed' => 'Ditunda',
                                        'completed' => 'Selesai',
                                    ];
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-4 py-3 font-semibold">
                                    <?= nama_bulan_indo($log['bulan']) ?> <?= $log['tahun'] ?>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $status_badge[$log['status']] ?>">
                                        <?= $status_label[$log['status']] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    <?= $log['dismissed_at'] ? date('d/m/Y H:i', strtotime($log['dismissed_at'])) : '-' ?>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    <?= $log['completed_at'] ? date('d/m/Y H:i', strtotime($log['completed_at'])) : '-' ?>
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

<script>
function confirmResetNotif(bulan, tahun) {
    Swal.fire({
        title: 'Reset Notifikasi?',
        html: `
            <p class="text-gray-600">Reset notifikasi untuk periode:</p>
            <p class="font-bold text-primary mt-2"><?= nama_bulan_indo($bulan_sekarang) ?> <?= $tahun_sekarang ?></p>
            <p class="text-xs text-amber-600 mt-3">
                <i class="fas fa-info-circle mr-1"></i>
                Notifikasi akan muncul lagi saat tanggal setting tiba
            </p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a365d',
        cancelButtonColor: '#718096',
        confirmButtonText: 'Ya, Reset',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?reset_notif=1&bulan=${bulan}&tahun=${tahun}`;
        }
    });
}
</script>

</body>
</html>