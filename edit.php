<?php
require 'config.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ Validasi ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0) {
    header("Location: dashboard.php");
    exit;
}

// ✅ Fetch data existing
$data = mysqli_query($conn, "SELECT * FROM inventaris WHERE id = $id")->fetch_assoc();
if(!$data) {
    header("Location: dashboard.php?action=notfound");
    exit;
}

// ✅ Handle Update
if(isset($_POST['update'])) {
    // Sanitize all inputs (sama seperti tambah.php)
    $kategori_id = mysqli_real_escape_string($conn, trim($_POST['kategori_id']));
    $ruangan_id = isset($_POST['ruangan_id']) ? (int)$_POST['ruangan_id'] : 0;
    
    // Auto-create kategori jika belum ada
    if ($kategori_id !== '') {
        $nama_kategori_esc = mysqli_real_escape_string($conn, $kategori_id);
        $cek_kategori_sql = "SELECT id FROM kategori_aset WHERE nama_kategori = '" . $nama_kategori_esc . "' COLLATE utf8mb4_general_ci LIMIT 1";
        $cek_kategori = mysqli_query($conn, $cek_kategori_sql);
        if ($cek_kategori && mysqli_num_rows($cek_kategori) === 0) {
            $kode_gen = strtoupper(preg_replace('/[^A-Z0-9]/', '', preg_replace('/\s+/', '', $kategori_id)));
            $kode_gen = substr($kode_gen, 0, 10);
            if ($kode_gen === '') $kode_gen = 'KAT' . substr(time(), -4);
            $kode_gen_esc = mysqli_real_escape_string($conn, $kode_gen);
            @mysqli_query($conn, "INSERT INTO kategori_aset (kode_kategori, nama_kategori, keterangan) VALUES ('{$kode_gen_esc}', '{$nama_kategori_esc}', '')");
        }
    }
    
    $kode_lokasi = mysqli_real_escape_string($conn, trim($_POST['kode_lokasi']));
    $nama_unit_lokasi = mysqli_real_escape_string($conn, trim($_POST['nama_unit_lokasi']));
    
    $sumber_pengadaan = mysqli_real_escape_string($conn, trim($_POST['sumber_pengadaan']));
    $bentuk_kontrak = mysqli_real_escape_string($conn, trim($_POST['bentuk_kontrak']));
    $no_dokumen_kontrak = mysqli_real_escape_string($conn, trim($_POST['no_dokumen_kontrak']));
    $tanggal_kontrak = !empty($_POST['tanggal_kontrak']) ? "'".$_POST['tanggal_kontrak']."'" : "NULL";
    $pihak_ke_3 = mysqli_real_escape_string($conn, trim($_POST['pihak_ke_3']));
    $no_bast = mysqli_real_escape_string($conn, trim($_POST['no_bast']));
    $tanggal_bast = !empty($_POST['tanggal_bast']) ? "'".$_POST['tanggal_bast']."'" : "NULL";
    
    $nama_ppk = mysqli_real_escape_string($conn, trim($_POST['nama_ppk']));
    $nama_pengurus_barang = mysqli_real_escape_string($conn, trim($_POST['nama_pengurus_barang']));
    $no_surat_pernyataan = mysqli_real_escape_string($conn, trim($_POST['no_surat_pernyataan']));
    $tanggal_pernyataan = !empty($_POST['tanggal_pernyataan']) ? "'".$_POST['tanggal_pernyataan']."'" : "NULL";
    
    $kode_sub_kegiatan = mysqli_real_escape_string($conn, trim($_POST['kode_sub_kegiatan']));
    $nama_sub_kegiatan = mysqli_real_escape_string($conn, trim($_POST['nama_sub_kegiatan']));
    $kode_rekening_belanja = mysqli_real_escape_string($conn, trim($_POST['kode_rekening_belanja']));
    $nama_rekening_belanja = mysqli_real_escape_string($conn, trim($_POST['nama_rekening_belanja']));
    
    $kode_barang_108 = mysqli_real_escape_string($conn, trim($_POST['kode_barang_108']));
    $nama_barang_108 = mysqli_real_escape_string($conn, trim($_POST['nama_barang_108']));
    $spesifikasi_nama_barang = mysqli_real_escape_string($conn, trim($_POST['spesifikasi_nama_barang']));
    $satuan = mysqli_real_escape_string($conn, trim($_POST['satuan']));
    $jumlah = (int)$_POST['jumlah'];
    
    $harga_raw = str_replace(['.', ','], '', $_POST['harga_satuan']);
    $harga_satuan = (float)$harga_raw;
    $total = $jumlah * $harga_satuan;
    
    $judul = mysqli_real_escape_string($conn, trim($_POST['judul']));
    $pencipta = mysqli_real_escape_string($conn, trim($_POST['pencipta']));
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan']));
    
    $query = "UPDATE inventaris SET
        ruangan_id = $ruangan_id,
        kode_lokasi = '$kode_lokasi',
        nama_unit_lokasi = '$nama_unit_lokasi',
        sumber_pengadaan = '$sumber_pengadaan',
        bentuk_kontrak = '$bentuk_kontrak',
        no_dokumen_kontrak = '$no_dokumen_kontrak',
        tanggal_kontrak = $tanggal_kontrak,
        pihak_ke_3 = '$pihak_ke_3',
        no_bast = '$no_bast',
        tanggal_bast = $tanggal_bast,
        nama_ppk = '$nama_ppk',
        nama_pengurus_barang = '$nama_pengurus_barang',
        no_surat_pernyataan = '$no_surat_pernyataan',
        tanggal_pernyataan = $tanggal_pernyataan,
        kode_sub_kegiatan = '$kode_sub_kegiatan',
        nama_sub_kegiatan = '$nama_sub_kegiatan',
        kode_rekening_belanja = '$kode_rekening_belanja',
        nama_rekening_belanja = '$nama_rekening_belanja',
        kategori_id = '$kategori_id',
        kode_barang_108 = '$kode_barang_108',
        nama_barang_108 = '$nama_barang_108',
        spesifikasi_nama_barang = '$spesifikasi_nama_barang',
        satuan = '$satuan',
        jumlah = '$jumlah',
        harga_satuan = '$harga_satuan',
        total = '$total',
        judul = '$judul',
        pencipta = '$pencipta',
        keterangan = '$keterangan'
        WHERE id = $id
    ";
    
    if(mysqli_query($conn, $query)) {
        header("Location: dashboard.php?action=updated");
        exit;
    } else {
        header("Location: edit.php?id=$id&error=db&msg=" . urlencode(mysqli_error($conn)));
        exit;
    }
}

$error_msg = '';
if(isset($_GET['error'])) {
    $error_msg = isset($_GET['msg']) ? $_GET['msg'] : 'Terjadi kesalahan database.';
}

$ruangan_list = mysqli_query($conn, "SELECT * FROM ruangan ORDER BY nama_ruangan ASC");

// ✅ Format data untuk pre-fill
$harga_formatted = number_format($data['harga_satuan'], 0, ',', '.');
$total_formatted = number_format($data['total'], 0, ',', '.');
$tanggal_kontrak_value = $data['tanggal_kontrak'] && $data['tanggal_kontrak'] != '0000-00-00' ? date('Y-m-d', strtotime($data['tanggal_kontrak'])) : '';
$tanggal_bast_value = $data['tanggal_bast'] && $data['tanggal_bast'] != '0000-00-00' ? date('Y-m-d', strtotime($data['tanggal_bast'])) : '';
$tanggal_pernyataan_value = $data['tanggal_pernyataan'] && $data['tanggal_pernyataan'] != '0000-00-00' ? date('Y-m-d', strtotime($data['tanggal_pernyataan'])) : '';
$created_at = $data['created_at'] ? date('d M Y, H:i', strtotime($data['created_at'])) : '-';
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Aset - <?= htmlspecialchars($data['nama_barang_108']) ?> - Inventaris SDN Curug 01</title>
    
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
        
        .input-modern:focus {
            box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
            border-color: #d97706;
        }
        
        .section-content {
            max-height: 2000px;
            overflow: hidden;
            transition: max-height 0.4s ease-out, opacity 0.3s ease;
            opacity: 1;
        }
        .section-content.collapsed {
            max-height: 0;
            opacity: 0;
        }
        
        html { scroll-behavior: smooth; }
        
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] { -moz-appearance: textfield; }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="asetFormApp()">
    
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
                        <span class="text-amber-600 font-semibold">Edit Aset</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-amber-600 dark:text-amber-400 flex items-center gap-2">
                        <i class="fas fa-edit"></i>
                        <span>Edit Aset</span>
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
        
        <div class="flex-1 p-4 lg:p-8">
            
            <?php if($error_msg): ?>
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg flex items-start gap-3"
                 x-data="{ show: true }" x-show="show">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mt-0.5"></i>
                <div class="flex-1">
                    <p class="font-semibold text-red-800 dark:text-red-300">Error!</p>
                    <p class="text-sm text-red-700 dark:text-red-400"><?= htmlspecialchars($error_msg) ?></p>
                </div>
                <button @click="show = false" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- ✅ Info Card Metadata -->
            <div class="mb-6 bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-4">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-white shadow-lg">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 dark:text-white">Informasi Aset</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Metadata aset yang sedang diedit</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-xs">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <p class="text-gray-500 dark:text-gray-400 mb-1"><i class="fas fa-hashtag mr-1"></i> ID Aset</p>
                        <p class="font-bold text-gray-800 dark:text-white font-mono">#<?= $id ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <p class="text-gray-500 dark:text-gray-400 mb-1"><i class="fas fa-box mr-1"></i> Nama Barang</p>
                        <p class="font-bold text-gray-800 dark:text-white truncate"><?= htmlspecialchars($data['nama_barang_108']) ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <p class="text-gray-500 dark:text-gray-400 mb-1"><i class="fas fa-calendar-plus mr-1"></i> Dibuat</p>
                        <p class="font-bold text-gray-800 dark:text-white"><?= $created_at ?></p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-3">
                        <p class="text-gray-500 dark:text-gray-400 mb-1"><i class="fas fa-coins mr-1"></i> Total Nilai</p>
                        <p class="font-bold text-amber-600 dark:text-amber-400">Rp <?= $total_formatted ?></p>
                    </div>
                </div>
            </div>
            
            <form method="POST" @submit="handleSubmit($event)" x-ref="asetForm" class="grid grid-cols-1 xl:grid-cols-4 gap-6">
                
                <div class="xl:col-span-3 space-y-5">
                    
                    <!-- Section 1: Informasi Lokasi -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden" id="section-lokasi">
                        <button type="button" @click="toggleSection('lokasi')" 
                                class="w-full p-5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white">Informasi Lokasi</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Pilih ruangan penempatan aset</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300" 
                               :class="sections.lokasi ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </button>
                        <div class="section-content" :class="{ 'collapsed': !sections.lokasi }">
                            <div class="p-5 pt-0 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-door-open text-blue-500"></i>
                                            Pilih Ruangan <span class="text-red-500">*</span>
                                        </label>
                                        <select name="ruangan_id" 
                                                @change="autoFillLokasi()"
                                                required
                                                class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                            <option value="">-- Pilih Ruangan --</option>
                                            <?php 
                                            mysqli_data_seek($ruangan_list, 0);
                                            while($r = mysqli_fetch_assoc($ruangan_list)): 
                                                $selected = ($r['id'] == $data['ruangan_id']) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $r['id'] ?>" 
                                                    data-kode="<?= htmlspecialchars($r['kode_ruangan']) ?>" 
                                                    data-nama="<?= htmlspecialchars($r['nama_ruangan']) ?>"
                                                    <?= $selected ?>>
                                                <?= htmlspecialchars($r['kode_ruangan']) ?> - <?= htmlspecialchars($r['nama_ruangan']) ?>
                                            </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-qrcode text-blue-500"></i>
                                            Kode Lokasi
                                        </label>
                                        <input type="text" 
                                               name="kode_lokasi" 
                                               id="kode_lokasi"
                                               value="<?= htmlspecialchars($data['kode_lokasi']) ?>" 
                                               required
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm font-mono">
                                    </div>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-building text-blue-500"></i>
                                        Nama Unit/Lokasi
                                    </label>
                                    <input type="text" 
                                           name="nama_unit_lokasi" 
                                           id="nama_unit_lokasi"
                                           value="<?= htmlspecialchars($data['nama_unit_lokasi']) ?>" 
                                           required
                                           class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 2: Informasi Pengadaan -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden" id="section-pengadaan">
                        <button type="button" @click="toggleSection('pengadaan')" 
                                class="w-full p-5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white">Informasi Pengadaan</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Sumber, kontrak, dan BAST</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300" 
                               :class="sections.pengadaan ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </button>
                        <div class="section-content" :class="{ 'collapsed': !sections.pengadaan }">
                            <div class="p-5 pt-0 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-hand-holding-usd text-emerald-500"></i>
                                            Sumber Pengadaan <span class="text-red-500">*</span>
                                        </label>
                                        <select name="sumber_pengadaan" required
                                                class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                            <option value="">Pilih Sumber</option>
                                            <?php 
                                            $sumber_list = ['Pemerintah', 'Sekolah', 'BOS', 'DAK', 'APBD'];
                                            foreach($sumber_list as $s): 
                                                $selected = ($data['sumber_pengadaan'] == $s) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $s ?>" <?= $selected ?>><?= $s ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-file-signature text-emerald-500"></i>
                                            Bentuk Kontrak
                                        </label>
                                        <select name="bentuk_kontrak"
                                                class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                            <option value="">Pilih</option>
                                            <?php 
                                            $bentuk_list = ['Surat Pesanan', 'Kontrak', 'SPK'];
                                            foreach($bentuk_list as $b): 
                                                $selected = ($data['bentuk_kontrak'] == $b) ? 'selected' : '';
                                            ?>
                                            <option value="<?= $b ?>" <?= $selected ?>><?= $b ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-hashtag text-emerald-500"></i>
                                            No Dokumen Kontrak
                                        </label>
                                        <input type="text" name="no_dokumen_kontrak"
                                               value="<?= htmlspecialchars($data['no_dokumen_kontrak']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-calendar text-emerald-500"></i>
                                            Tanggal Kontrak
                                        </label>
                                        <input type="date" name="tanggal_kontrak"
                                               value="<?= $tanggal_kontrak_value ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-store text-emerald-500"></i>
                                            Pihak ke-3 (Vendor)
                                        </label>
                                        <input type="text" name="pihak_ke_3"
                                               value="<?= htmlspecialchars($data['pihak_ke_3']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-file-alt text-emerald-500"></i>
                                            No BAST
                                        </label>
                                        <input type="text" name="no_bast"
                                               value="<?= htmlspecialchars($data['no_bast']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div class="max-w-xs">
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-calendar-check text-emerald-500"></i>
                                        Tanggal BAST
                                    </label>
                                    <input type="date" name="tanggal_bast"
                                           value="<?= $tanggal_bast_value ?>"
                                           class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 3: Informasi Pejabat -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden" id="section-pejabat">
                        <button type="button" @click="toggleSection('pejabat')" 
                                class="w-full p-5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white">Informasi Pejabat</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">PPK dan pengurus barang (opsional)</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300" 
                               :class="sections.pejabat ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </button>
                        <div class="section-content" :class="{ 'collapsed': !sections.pejabat }">
                            <div class="p-5 pt-0 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-user-shield text-purple-500"></i>
                                            Nama PPK
                                        </label>
                                        <input type="text" name="nama_ppk"
                                               value="<?= htmlspecialchars($data['nama_ppk']) ?>"
                                               placeholder="Kosongkan jika belum ada"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-user-cog text-purple-500"></i>
                                            Nama Pengurus Barang
                                        </label>
                                        <input type="text" name="nama_pengurus_barang"
                                               value="<?= htmlspecialchars($data['nama_pengurus_barang']) ?>"
                                               placeholder="Kosongkan jika belum ada"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-file-contract text-purple-500"></i>
                                            No Surat Pernyataan
                                        </label>
                                        <input type="text" name="no_surat_pernyataan"
                                               value="<?= htmlspecialchars($data['no_surat_pernyataan']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-calendar text-purple-500"></i>
                                            Tanggal Pernyataan
                                        </label>
                                        <input type="date" name="tanggal_pernyataan"
                                               value="<?= $tanggal_pernyataan_value ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 4: Kegiatan & Rekening -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden" id="section-kegiatan">
                        <button type="button" @click="toggleSection('kegiatan')" 
                                class="w-full p-5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-tasks"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white">Kegiatan & Rekening</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Sub kegiatan dan rekening belanja</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300" 
                               :class="sections.kegiatan ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </button>
                        <div class="section-content" :class="{ 'collapsed': !sections.kegiatan }">
                            <div class="p-5 pt-0 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-hashtag text-amber-500"></i>
                                            Kode Sub Kegiatan
                                        </label>
                                        <input type="text" name="kode_sub_kegiatan"
                                               value="<?= htmlspecialchars($data['kode_sub_kegiatan']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm font-mono">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-list-ol text-amber-500"></i>
                                            Nama Sub Kegiatan
                                        </label>
                                        <input type="text" name="nama_sub_kegiatan"
                                               value="<?= htmlspecialchars($data['nama_sub_kegiatan']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-coins text-amber-500"></i>
                                            Kode Rekening Belanja
                                        </label>
                                        <input type="text" name="kode_rekening_belanja" 
                                               value="<?= htmlspecialchars($data['kode_rekening_belanja']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm font-mono">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-file-invoice-dollar text-amber-500"></i>
                                            Nama Rekening Belanja
                                        </label>
                                        <input type="text" name="nama_rekening_belanja" 
                                               value="<?= htmlspecialchars($data['nama_rekening_belanja']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 5: Informasi Barang -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border-2 border-amber-500/30 dark:border-amber-500/40 overflow-hidden" id="section-barang">
                        <div class="p-5 flex items-center justify-between bg-gradient-to-r from-amber-500/5 to-transparent">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-box"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white flex items-center gap-2">
                                        Informasi Barang
                                        <span class="text-[10px] px-2 py-0.5 bg-amber-500/10 text-amber-600 rounded-full font-semibold">EDIT</span>
                                    </h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Permendagri 108 - Detail aset</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300 cursor-pointer" 
                               @click="toggleSection('barang')"
                               :class="sections.barang ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </div>
                        <div class="section-content" :class="{ 'collapsed': !sections.barang }">
                            <div class="p-5 pt-0 space-y-4">
                                
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-tags text-amber-500"></i>
                                        Kategori Aset <span class="text-red-500">*</span>
                                    </label>
                                    <select name="kategori_id" required
                                            class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-amber-500 transition-all text-sm">
                                        <option value="">-- Pilih Kategori Aset --</option>
                                        <?php 
                                        $kategori_list = [
                                            'Buku & Bahan Ajar',
                                            'Alat Tulis Kantor (ATK)',
                                            'Perlengkapan Komputer & Printer',
                                            'Perlengkapan Kebersihan',
                                            'Perlengkapan Kesehatan',
                                            'Peralatan Olahraga',
                                            'Peralatan dan Sarana Pendukung Sekolah'
                                        ];
                                        $kategori_icons = ['📚', '✏️', '💻', '🧹', '🏥', '⚽', '🏫'];
                                        foreach($kategori_list as $idx => $kat): 
                                            $selected = ($data['kategori_id'] == $kat) ? 'selected' : '';
                                        ?>
                                        <option value="<?= $kat ?>" <?= $selected ?>><?= $kategori_icons[$idx] ?> <?= $kat ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="text-xs text-gray-400 mt-1.5">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Pilih salah satu dari 7 kategori aset standar sekolah
                                    </p>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-barcode text-amber-500"></i>
                                            Kode Barang (108)
                                        </label>
                                        <input type="text" name="kode_barang_108"
                                               value="<?= htmlspecialchars($data['kode_barang_108']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm font-mono">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-box-open text-amber-500"></i>
                                            Nama Barang <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="nama_barang_108" required
                                               value="<?= htmlspecialchars($data['nama_barang_108']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="flex items-center justify-between mb-2">
                                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            <i class="fas fa-list-ul text-amber-500"></i>
                                            Spesifikasi Nama Barang <span class="text-red-500">*</span>
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            <span x-text="form.spesifikasi.length"></span>/500
                                        </span>
                                    </label>
                                    <textarea name="spesifikasi_nama_barang" 
                                              x-model="form.spesifikasi"
                                              @input="if(form.spesifikasi.length > 500) form.spesifikasi = form.spesifikasi.substring(0, 500)"
                                              rows="3" required
                                              placeholder="Contoh: Merk Toshiba, warna hitam, ukuran 14 inch, dll..."
                                              class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm resize-none"></textarea>
                                </div>
                                
                                <div class="bg-gradient-to-br from-amber-500/5 to-orange-500/10 dark:from-amber-500/20 dark:to-orange-500/10 rounded-lg p-4 border border-amber-500/20">
                                    <p class="text-xs font-semibold text-amber-700 dark:text-amber-300 mb-3 flex items-center gap-2">
                                        <i class="fas fa-calculator"></i>
                                        Perhitungan Nilai Aset
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                                                Satuan <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="satuan" 
                                                   value="<?= htmlspecialchars($data['satuan']) ?>"
                                                   placeholder="Unit/Buah/Set" required
                                                   class="input-modern w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                                                Jumlah <span class="text-red-500">*</span>
                                            </label>
                                            <input type="number" name="jumlah" 
                                                   x-model.number="form.jumlah"
                                                   @input="hitungTotal()"
                                                   min="1" required
                                                   class="input-modern w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                                                Harga Satuan <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" name="harga_satuan" 
                                                   x-model="form.harga_satuan_raw"
                                                   @input="formatHarga(); hitungTotal()"
                                                   @blur="formatHarga()"
                                                   required
                                                   placeholder="Rp 0"
                                                   class="input-modern w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                        </div>
                                        <div>
                                            <label class="text-xs font-semibold text-gray-700 dark:text-gray-200 mb-1.5 block">
                                                Total
                                            </label>
                                            <div class="w-full px-3 py-2 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg text-sm font-bold text-amber-600 dark:text-amber-400">
                                                <span x-text="form.total_formatted"></span>
                                            </div>
                                            <input type="hidden" name="total" :value="form.total">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Section 6: Informasi Tambahan -->
                    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 overflow-hidden" id="section-tambahan">
                        <button type="button" @click="toggleSection('tambahan')" 
                                class="w-full p-5 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-gray-500 to-gray-600 flex items-center justify-center text-white shadow-md">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="text-left">
                                    <h3 class="font-bold text-gray-800 dark:text-white">Informasi Tambahan</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Opsional - untuk detail ekstra</p>
                                </div>
                            </div>
                            <i class="fas transition-transform duration-300" 
                               :class="sections.tambahan ? 'fa-chevron-up' : 'fa-chevron-down rotate-180'"></i>
                        </button>
                        <div class="section-content" :class="{ 'collapsed': !sections.tambahan }">
                            <div class="p-5 pt-0 space-y-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-heading text-gray-500"></i>
                                            Judul
                                        </label>
                                        <input type="text" name="judul"
                                               value="<?= htmlspecialchars($data['judul']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-pen-fancy text-gray-500"></i>
                                            Pencipta
                                        </label>
                                        <input type="text" name="pencipta"
                                               value="<?= htmlspecialchars($data['pencipta']) ?>"
                                               class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-note-sticky text-gray-500"></i>
                                        Keterangan
                                    </label>
                                    <textarea name="keterangan" rows="2"
                                              class="input-modern w-full px-4 py-2.5 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none transition-all text-sm resize-none"><?= htmlspecialchars($data['keterangan']) ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>
                
                <!-- Progress Sidebar -->
                <div class="xl:col-span-1">
                    <div class="sticky top-24 space-y-4">
                        
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-5">
                            <div class="flex items-center justify-between mb-3">
                                <h3 class="font-bold text-gray-800 dark:text-white text-sm flex items-center gap-2">
                                    <i class="fas fa-tasks text-amber-500"></i>
                                    Progress
                                </h3>
                                <span class="text-xs font-bold" 
                                      :class="completionPercent === 100 ? 'text-green-500' : (completionPercent >= 50 ? 'text-amber-500' : 'text-red-500')"
                                      x-text="completionPercent + '%'"></span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden mb-4">
                                <div class="h-full rounded-full transition-all duration-500"
                                     :class="completionPercent === 100 ? 'bg-green-500' : (completionPercent >= 50 ? 'bg-amber-500' : 'bg-amber-600')"
                                     :style="'width: ' + completionPercent + '%'"></div>
                            </div>
                            
                            <div class="space-y-2">
                                <a href="#section-lokasi" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.lokasi ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.lokasi ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Lokasi</span>
                                </a>
                                <a href="#section-pengadaan" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.pengadaan ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.pengadaan ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Pengadaan</span>
                                </a>
                                <a href="#section-pejabat" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.pejabat ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.pejabat ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Pejabat</span>
                                </a>
                                <a href="#section-kegiatan" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.kegiatan ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.kegiatan ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Kegiatan</span>
                                </a>
                                <a href="#section-barang" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.barang ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.barang ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Barang</span>
                                    <span class="ml-auto text-[10px] px-1.5 py-0.5 bg-red-100 text-red-600 rounded font-semibold">Wajib</span>
                                </a>
                                <a href="#section-tambahan" class="flex items-center gap-2 p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors text-xs">
                                    <i :class="sectionStatus.tambahan ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas"></i>
                                    <span :class="sectionStatus.tambahan ? 'text-gray-700 dark:text-gray-200 font-medium' : 'text-gray-500'">Tambahan</span>
                                    <span class="ml-auto text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded font-semibold">Opsional</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-xl border border-amber-200 dark:border-amber-800 p-4">
                            <p class="text-xs font-semibold text-amber-900 dark:text-amber-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-lightbulb"></i>
                                Tips Edit
                            </p>
                            <ul class="text-xs text-amber-700 dark:text-amber-400 space-y-1.5">
                                <li class="flex gap-2"><i class="fas fa-check text-[10px] mt-0.5"></i> Semua field sudah terisi otomatis</li>
                                <li class="flex gap-2"><i class="fas fa-check text-[10px] mt-0.5"></i> Ubah field yang perlu diupdate</li>
                                <li class="flex gap-2"><i class="fas fa-check text-[10px] mt-0.5"></i> Total otomatis dihitung ulang</li>
                                <li class="flex gap-2"><i class="fas fa-check text-[10px] mt-0.5"></i> <kbd class="px-1 bg-white dark:bg-gray-700 rounded text-[9px]">Ctrl+Enter</kbd> untuk update</li>
                            </ul>
                        </div>
                        
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700 p-4 space-y-2">
                            <button type="submit" name="update"
                                    :disabled="isSubmitting"
                                    class="w-full px-4 py-3 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="!isSubmitting">
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-save"></i>
                                        Update Aset
                                    </span>
                                </template>
                                <template x-if="isSubmitting">
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-spinner fa-spin"></i>
                                        Mengupdate...
                                    </span>
                                </template>
                            </button>
                            <a href="dashboard.php" 
                               class="w-full px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                <i class="fas fa-times"></i>
                                Batal
                            </a>
                        </div>
                    </div>
                </div>
                
            </form>
        </div>
    </main>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function asetFormApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        isSubmitting: false,
        
        sections: {
            lokasi: true,
            pengadaan: true,
            pejabat: false,
            kegiatan: false,
            barang: true,
            tambahan: false
        },
        
        // ✅ Pre-fill form dengan data existing
        form: {
            spesifikasi: '<?= addslashes($data['spesifikasi_nama_barang']) ?>',
            jumlah: <?= (int)$data['jumlah'] ?>,
            harga_satuan_raw: '<?= $harga_formatted ?>',
            total: <?= (float)$data['total'] ?>,
            total_formatted: 'Rp <?= $total_formatted ?>'
        },
        
        sectionStatus: {
            lokasi: false,
            pengadaan: false,
            pejabat: false,
            kegiatan: false,
            barang: false,
            tambahan: false
        },
        completionPercent: 0,
        
        updateProgress() {
            const form = this.$refs.asetForm;
            if (!form) return;
            
            this.sectionStatus = {
                lokasi: !!(form.ruangan_id?.value && form.nama_unit_lokasi?.value),
                pengadaan: !!form.sumber_pengadaan?.value,
                pejabat: !!(form.nama_ppk?.value && form.nama_pengurus_barang?.value),
                kegiatan: !!form.kode_rekening_belanja?.value,
                barang: !!(form.kategori_id?.value 
                        && form.nama_barang_108?.value 
                        && form.spesifikasi_nama_barang?.value 
                        && form.satuan?.value 
                        && form.jumlah?.value 
                        && form.harga_satuan?.value),
                tambahan: !!(form.judul?.value || form.keterangan?.value)
            };
            
            const status = this.sectionStatus;
            const required = ['lokasi', 'pengadaan', 'barang'];
            const optional = ['pejabat', 'kegiatan', 'tambahan'];
            
            let done = 0;
            required.forEach(s => { if (status[s]) done += 1; });
            optional.forEach(s => { if (status[s]) done += 0.5; });
            
            this.completionPercent = Math.round((done / (required.length + optional.length * 0.5)) * 100);
        },
        
        init() {
            const form = this.$refs.asetForm;
            if (form) {
                form.addEventListener('input', () => this.updateProgress());
                form.addEventListener('change', () => this.updateProgress());
                setTimeout(() => this.updateProgress(), 150);
            }
            
            // ✅ Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.$refs.asetForm.requestSubmit();
                }
            });
        },
        
        toggleSection(name) {
            this.sections[name] = !this.sections[name];
        },
        
        autoFillLokasi() {
            const select = this.$refs.asetForm.ruangan_id;
            const option = select.options[select.selectedIndex];
            const namaRuangan = option.getAttribute('data-nama');
            const kodeRuangan = option.getAttribute('data-kode');
            
            if (namaRuangan) {
                this.$refs.asetForm.nama_unit_lokasi.value = 'UPTD SDN Curug 1 - ' + namaRuangan;
                this.$refs.asetForm.kode_lokasi.value = kodeRuangan || '01.00.00.0055';
                this.showToast('Lokasi otomatis terisi', 'success');
                this.updateProgress();
            }
        },
        
        formatHarga() {
            let raw = this.form.harga_satuan_raw.replace(/[^0-9]/g, '');
            if (!raw) {
                this.form.harga_satuan_raw = '';
                return;
            }
            this.form.harga_satuan_raw = parseInt(raw).toLocaleString('id-ID');
        },
        
        hitungTotal() {
            const jumlah = parseInt(this.form.jumlah) || 0;
            const harga = parseInt(this.form.harga_satuan_raw.replace(/[^0-9]/g, '')) || 0;
            const total = jumlah * harga;
            
            this.form.total = total;
            this.form.total_formatted = total > 0 ? 'Rp ' + total.toLocaleString('id-ID') : 'Rp 0';
        },
        
        handleSubmit(event) {
            const form = event.target;
            const required = [
                { name: 'ruangan_id', label: 'Ruangan' },
                { name: 'sumber_pengadaan', label: 'Sumber Pengadaan' },
                { name: 'kategori_id', label: 'Kategori Aset' },
                { name: 'nama_barang_108', label: 'Nama Barang' },
                { name: 'spesifikasi_nama_barang', label: 'Spesifikasi' },
                { name: 'satuan', label: 'Satuan' },
                { name: 'jumlah', label: 'Jumlah' },
                { name: 'harga_satuan', label: 'Harga Satuan' }
            ];
            
            for (const field of required) {
                if (!form[field.name].value) {
                    event.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Data Belum Lengkap',
                        html: `Field <strong>${field.label}</strong> wajib diisi`,
                        confirmButtonColor: '#d97706'
                    });
                    form[field.name].focus();
                    return;
                }
            }
            
            event.preventDefault();
            Swal.fire({
                title: 'Update Data Aset?',
                html: `
                    <div class="text-left mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800">
                        <p class="text-xs text-amber-700 dark:text-amber-300 mb-2"><i class="fas fa-info-circle mr-1"></i> Data yang akan diupdate:</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200"><strong>Kategori:</strong> ${form.kategori_id.value}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200 mt-1"><strong>Barang:</strong> ${form.nama_barang_108.value}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200 mt-1"><strong>Jumlah:</strong> ${form.jumlah.value} ${form.satuan.value}</p>
                        <p class="text-sm text-gray-700 dark:text-gray-200 mt-1"><strong>Total:</strong> ${this.form.total_formatted}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#d97706',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Update!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.isSubmitting = true;
                    if (!form.querySelector('input[name="update"]')) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'update';
                        hidden.value = '1';
                        form.appendChild(hidden);
                    }
                    form.submit();
                }
            });
        },
        
        toggleDarkMode() {
            this.darkMode = !this.darkMode;
            localStorage.setItem('darkMode', this.darkMode);
        },
        
        confirmLogout() {
            Swal.fire({
                title: 'Logout?',
                text: 'Data yang belum disimpan mungkin akan hilang',
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
            }, 3000);
        }
    };
}
</script>

</body>
</html>