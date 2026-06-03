<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

if(isset($_POST['simpan'])) {
    // Sanitize input
    $kode_ruangan = mysqli_real_escape_string($conn, strtoupper(trim($_POST['kode_ruangan'])));
    $nama_ruangan = mysqli_real_escape_string($conn, trim($_POST['nama_ruangan']));
    $lantai = (int)$_POST['lantai'];
    $gedung = mysqli_real_escape_string($conn, trim($_POST['gedung']));
    $kapasitas = (int)$_POST['kapasitas'];
    $fungsi_ruangan = mysqli_real_escape_string($conn, trim($_POST['fungsi_ruangan']));
    $penanggung_jawab = mysqli_real_escape_string($conn, trim($_POST['penanggung_jawab']));
    $keterangan = mysqli_real_escape_string($conn, trim($_POST['keterangan']));
    
    // Check duplicate kode
    $check = mysqli_query($conn, "SELECT id FROM ruangan WHERE kode_ruangan = '$kode_ruangan'");
    if(mysqli_num_rows($check) > 0) {
        header("Location: tambah_ruangan.php?error=duplicate");
        exit;
    }
    
    $query = "INSERT INTO ruangan SET
        kode_ruangan = '$kode_ruangan',
        nama_ruangan = '$nama_ruangan',
        lantai = '$lantai',
        gedung = '$gedung',
        kapasitas = '$kapasitas',
        fungsi_ruangan = '$fungsi_ruangan',
        penanggung_jawab = '$penanggung_jawab',
        keterangan = '$keterangan'
    ";
    
    if(mysqli_query($conn, $query)) {
        header("Location: ruangan.php?action=added");
        exit;
    } else {
        header("Location: tambah_ruangan.php?error=db");
        exit;
    }
}

// Get error message if any
$error_msg = '';
if(isset($_GET['error'])) {
    if($_GET['error'] === 'duplicate') {
        $error_msg = 'Kode ruangan sudah digunakan! Silakan gunakan kode lain.';
    } elseif($_GET['error'] === 'db') {
        $error_msg = 'Terjadi kesalahan database. Silakan coba lagi.';
    }
}
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Ruangan - Inventaris SDN Curug 01</title>
    
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
                        }
                    },
                    fontFamily: {
                        sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif']
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out',
                        'slide-in-left': 'slideInLeft 0.3s ease-out',
                        'shake': 'shake 0.5s'
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
                        },
                        shake: {
                            '0%, 100%': { transform: 'translateX(0)' },
                            '25%': { transform: 'translateX(-5px)' },
                            '75%': { transform: 'translateX(5px)' }
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #1a365d; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #0f2744; }
        .dark ::-webkit-scrollbar-track { background: #2d3748; }
        
        * { transition: background-color 0.3s ease, color 0.2s ease, border-color 0.3s ease; }
        
        /* Custom input focus ring */
        .input-modern:focus {
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.15);
        }
        
        /* Section connector line */
        .section-connector::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 40px;
            bottom: -20px;
            width: 2px;
            background: linear-gradient(to bottom, #1a365d, transparent);
        }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="formApp()">
    
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
                      <?= ($current_page == $menu[0] || ($current_page == 'tambah_ruangan.php' && $menu[0] == 'ruangan.php') || ($current_page == 'edit_ruangan.php' && $menu[0] == 'ruangan.php')) ? 'bg-white text-primary font-semibold shadow-lg' : 'text-white/80' ?>">
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
                        <a href="ruangan.php" class="hover:text-primary">Ruangan</a>
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="text-primary font-semibold">Tambah</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-plus-circle"></i>
                        <span>Tambah Ruangan Baru</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2 lg:gap-3">
                <button @click="toggleDarkMode()" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <a href="ruangan.php" 
                   class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all">
                    <i class="fas fa-arrow-left"></i>
                    <span class="text-sm font-medium">Kembali</span>
                </a>
            </div>
        </header>
        
        <!-- Content Area -->
        <div class="flex-1 p-4 lg:p-8 animate-fade-in">
            
            <!-- Error Alert -->
            <?php if($error_msg): ?>
            <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 rounded-lg flex items-start gap-3 animate-slide-up"
                 x-data="{ show: true }" x-show="show">
                <i class="fas fa-exclamation-circle text-red-500 text-xl mt-0.5"></i>
                <div class="flex-1">
                    <p class="font-semibold text-red-800 dark:text-red-300">Error!</p>
                    <p class="text-sm text-red-700 dark:text-red-400"><?= $error_msg ?></p>
                </div>
                <button @click="show = false" class="text-red-500 hover:text-red-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endif; ?>
            
            <form method="POST" @submit="handleSubmit($event)" x-ref="roomForm" class="space-y-6">
                
                <!-- Main Grid: Form + Preview -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    
                    <!-- Form Column -->
                    <div class="xl:col-span-2 space-y-6">
                        
                        <!-- Section 1: Informasi Dasar -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-blue-500/5 to-transparent">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white shadow-lg">
                                        <span class="font-bold">1</span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Informasi Dasar</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Identitas utama ruangan</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-5 lg:p-6 space-y-5">
                                <!-- Kode Ruangan -->
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-qrcode text-primary"></i>
                                        Kode Ruangan <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input type="text" 
                                               name="kode_ruangan" 
                                               x-model="form.kode_ruangan"
                                               @input="form.kode_ruangan = form.kode_ruangan.toUpperCase()"
                                               @blur="validateKode()"
                                               placeholder="Contoh: R-001" 
                                               required
                                               maxlength="20"
                                               :class="validation.kode.status"
                                               class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border rounded-lg focus:outline-none focus:border-primary transition-all text-sm uppercase tracking-wider font-mono">
                                        <div x-show="validation.kode.status === 'border-green-500'" 
                                             class="absolute right-3 top-1/2 -translate-y-1/2 text-green-500">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div x-show="validation.kode.status === 'border-red-500'" 
                                             class="absolute right-3 top-1/2 -translate-y-1/2 text-red-500">
                                            <i class="fas fa-times-circle"></i>
                                        </div>
                                    </div>
                                    <p x-show="validation.kode.message" 
                                       x-text="validation.kode.message"
                                       class="text-xs mt-1.5"
                                       :class="validation.kode.status === 'border-green-500' ? 'text-green-600' : 'text-red-500'"></p>
                                    <p class="text-xs text-gray-400 mt-1.5">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Gunakan format unik, contoh: R-001, LAB-01, PERP-01
                                    </p>
                                </div>
                                
                                <!-- Nama Ruangan -->
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-door-open text-primary"></i>
                                        Nama Ruangan <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" 
                                           name="nama_ruangan" 
                                           x-model="form.nama_ruangan"
                                           placeholder="Contoh: Kelas 1A, Lab Komputer, Perpustakaan" 
                                           required
                                           maxlength="100"
                                           class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary transition-all text-sm">
                                    <div class="flex justify-between mt-1.5">
                                        <p class="text-xs text-gray-400">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            Nama yang mudah dikenali
                                        </p>
                                        <p class="text-xs" :class="form.nama_ruangan.length > 90 ? 'text-amber-500' : 'text-gray-400'">
                                            <span x-text="form.nama_ruangan.length"></span>/100
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Lokasi & Kapasitas -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-emerald-500/5 to-transparent">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white shadow-lg">
                                        <span class="font-bold">2</span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Lokasi & Kapasitas</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Detail posisi dan daya tampung</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-5 lg:p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <!-- Gedung -->
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-building text-emerald-500"></i>
                                            Gedung <span class="text-red-500">*</span>
                                        </label>
                                        <select name="gedung" 
                                                x-model="form.gedung"
                                                required
                                                class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary transition-all text-sm">
                                            <option value="">-- Pilih Gedung --</option>
                                            <option value="Gedung A">Gedung A</option>
                                            <option value="Gedung B">Gedung B</option>
                                            <option value="Gedung C">Gedung C</option>
                                            <option value="Gedung D">Gedung D</option>
                                            <option value="Gedung E">Gedung E</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Lantai -->
                                    <div>
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-layer-group text-emerald-500"></i>
                                            Lantai <span class="text-red-500">*</span>
                                        </label>
                                        <div class="grid grid-cols-3 gap-2">
                                            <template x-for="i in [1, 2, 3]">
                                                <button type="button" 
                                                        @click="form.lantai = i"
                                                        :class="form.lantai == i ? 'bg-primary text-white border-primary shadow-md' : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:border-primary'"
                                                        class="py-3 px-2 border rounded-lg text-sm font-semibold transition-all">
                                                    <i class="fas fa-arrow-up mr-1 text-xs"></i>
                                                    <span x-text="i"></span>
                                                </button>
                                            </template>
                                        </div>
                                        <input type="hidden" name="lantai" :value="form.lantai" required>
                                    </div>
                                    
                                    <!-- Kapasitas -->
                                    <div class="md:col-span-2">
                                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                            <i class="fas fa-users text-emerald-500"></i>
                                            Kapasitas (Orang)
                                        </label>
                                        <div class="relative">
                                            <input type="range" 
                                                   x-model="form.kapasitas"
                                                   min="0" 
                                                   max="100" 
                                                   step="1"
                                                   class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer accent-primary">
                                            <div class="flex justify-between mt-2">
                                                <span class="text-xs text-gray-400">0</span>
                                                <span class="text-sm font-bold text-primary">
                                                    <span x-text="form.kapasitas"></span> Orang
                                                </span>
                                                <span class="text-xs text-gray-400">100</span>
                                            </div>
                                        </div>
                                        <input type="hidden" name="kapasitas" :value="form.kapasitas">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Informasi Tambahan -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                            <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-amber-500/5 to-transparent">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-amber-500 to-amber-600 flex items-center justify-center text-white shadow-lg">
                                        <span class="font-bold">3</span>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Informasi Tambahan</h3>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Fungsi dan penanggung jawab</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="p-5 lg:p-6 space-y-5">
                                <!-- Fungsi Ruangan -->
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-clipboard-list text-amber-500"></i>
                                        Fungsi Ruangan
                                    </label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                                        <?php 
                                        $fungsi_list = [
                                            ['Kegiatan Belajar Mengajar', 'fa-chalkboard', 'KBM'],
                                            ['Ruang Kerja Guru', 'fa-briefcase', 'Kerja'],
                                            ['Praktikum', 'fa-flask', 'Praktikum'],
                                            ['Perpustakaan', 'fa-book', 'Perpus'],
                                            ['Kesehatan', 'fa-heart-pulse', 'UKS'],
                                            ['Ibadah', 'fa-mosque', 'Ibadah'],
                                            ['Olahraga', 'fa-basketball', 'Olahraga'],
                                            ['Lainnya', 'fa-ellipsis', 'Lainnya']
                                        ];
                                        foreach($fungsi_list as $fungsi): ?>
                                        <button type="button" 
                                                @click="form.fungsi_ruangan = '<?= $fungsi[0] ?>'"
                                                :class="form.fungsi_ruangan === '<?= $fungsi[0] ?>' ? 'bg-amber-500 text-white border-amber-500 shadow-md' : 'bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-600 hover:border-amber-500'"
                                                class="p-3 border rounded-lg text-xs font-medium transition-all flex flex-col items-center gap-1.5">
                                            <i class="fas <?= $fungsi[1] ?> text-base"></i>
                                            <span><?= $fungsi[2] ?></span>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <input type="hidden" name="fungsi_ruangan" :value="form.fungsi_ruangan">
                                </div>
                                
                                <!-- Penanggung Jawab -->
                                <div>
                                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200 mb-2">
                                        <i class="fas fa-user-tie text-amber-500"></i>
                                        Penanggung Jawab
                                    </label>
                                    <input type="text" 
                                           name="penanggung_jawab" 
                                           x-model="form.penanggung_jawab"
                                           placeholder="Nama Guru/Staf yang bertanggung jawab" 
                                           class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary transition-all text-sm">
                                </div>
                                
                                <!-- Keterangan -->
                                <div>
                                    <label class="flex items-center justify-between mb-2">
                                        <span class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                                            <i class="fas fa-note-sticky text-amber-500"></i>
                                            Keterangan
                                        </span>
                                        <span class="text-xs" :class="form.keterangan.length > 200 ? 'text-amber-500' : 'text-gray-400'">
                                            <span x-text="form.keterangan.length"></span>/250
                                        </span>
                                    </label>
                                    <textarea name="keterangan" 
                                              x-model="form.keterangan"
                                              @input="if(form.keterangan.length > 250) form.keterangan = form.keterangan.substring(0, 250)"
                                              rows="4" 
                                              maxlength="250"
                                              placeholder="Catatan tambahan tentang ruangan ini (opsional)..."
                                              class="input-modern w-full px-4 py-3 bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg focus:outline-none focus:border-primary transition-all text-sm resize-none"></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 p-5 lg:p-6">
                            <div class="flex flex-col sm:flex-row gap-3 justify-end">
                                <a href="ruangan.php" 
                                   class="px-6 py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                    <i class="fas fa-times"></i>
                                    Batal
                                </a>
                                <button type="button" 
                                        @click="resetForm()"
                                        class="px-6 py-3 bg-amber-100 hover:bg-amber-500 text-amber-700 hover:text-white rounded-lg transition-all flex items-center justify-center gap-2 text-sm font-medium">
                                    <i class="fas fa-rotate-left"></i>
                                    Reset
                                </button>
                                <button type="submit" 
                                        name="simpan"
                                        :disabled="isSubmitting"
                                        class="px-8 py-3 bg-gradient-to-r from-primary to-primary-light hover:from-primary-dark hover:to-primary text-white rounded-lg shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2 text-sm font-semibold disabled:opacity-50 disabled:cursor-not-allowed">
                                    <template x-if="!isSubmitting">
                                        <span class="flex items-center gap-2">
                                            <i class="fas fa-save"></i>
                                            Simpan Ruangan
                                        </span>
                                    </template>
                                    <template x-if="isSubmitting">
                                        <span class="flex items-center gap-2">
                                            <i class="fas fa-spinner fa-spin"></i>
                                            Menyimpan...
                                        </span>
                                    </template>
                                </button>
                            </div>
                            <p class="text-xs text-gray-400 text-center mt-3">
                                <i class="fas fa-keyboard mr-1"></i>
                                Tekan <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px]">Ctrl</kbd> + <kbd class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-[10px]">Enter</kbd> untuk menyimpan
                            </p>
                        </div>
                    </div>
                    
                    <!-- Preview Column (Sticky) -->
                    <div class="xl:col-span-1">
                        <div class="sticky top-24 space-y-4">
                            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-eye text-primary"></i>
                                            <h3 class="font-bold text-gray-800 dark:text-white text-sm">Live Preview</h3>
                                        </div>
                                        <span class="text-[10px] px-2 py-0.5 bg-green-100 text-green-700 rounded-full font-semibold">
                                            <i class="fas fa-circle text-[6px] animate-pulse mr-1"></i>
                                            LIVE
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="p-5">
                                    <!-- Preview Card -->
                                    <div class="relative bg-gradient-to-br from-primary to-primary-dark rounded-xl p-5 text-white shadow-xl overflow-hidden">
                                        <div class="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -translate-y-16 translate-x-16"></div>
                                        <div class="absolute bottom-0 left-0 w-24 h-24 bg-white/5 rounded-full translate-y-12 -translate-x-12"></div>
                                        
                                        <div class="relative">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="p-2.5 bg-white/20 backdrop-blur rounded-lg">
                                                    <i class="fas fa-door-open text-xl"></i>
                                                </div>
                                                <span class="text-[10px] px-2 py-1 bg-white/20 backdrop-blur rounded-full font-mono" 
                                                      x-text="form.kode_ruangan || 'R-XXX'"></span>
                                            </div>
                                            
                                            <h4 class="text-lg font-bold mb-1 line-clamp-1" 
                                                x-text="form.nama_ruangan || 'Nama Ruangan'"></h4>
                                            
                                            <div class="flex items-center gap-1.5 text-xs text-white/80 mb-4">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span x-text="form.gedung || 'Gedung belum dipilih'"></span>
                                                <span x-show="form.lantai">• Lantai <span x-text="form.lantai"></span></span>
                                            </div>
                                            
                                            <div class="grid grid-cols-2 gap-2 pt-3 border-t border-white/20">
                                                <div>
                                                    <p class="text-[10px] text-white/60 uppercase tracking-wider">Kapasitas</p>
                                                    <p class="text-sm font-bold">
                                                        <i class="fas fa-users text-xs mr-1"></i>
                                                        <span x-text="form.kapasitas"></span> Orang
                                                    </p>
                                                </div>
                                                <div>
                                                    <p class="text-[10px] text-white/60 uppercase tracking-wider">Fungsi</p>
                                                    <p class="text-sm font-bold truncate" 
                                                       x-text="form.fungsi_ruangan || '-'"></p>
                                                </div>
                                            </div>
                                            
                                            <div x-show="form.penanggung_jawab" class="mt-3 pt-3 border-t border-white/20">
                                                <p class="text-[10px] text-white/60 uppercase tracking-wider">Penanggung Jawab</p>
                                                <p class="text-sm font-semibold flex items-center gap-2 mt-1">
                                                    <i class="fas fa-user-tie"></i>
                                                    <span x-text="form.penanggung_jawab"></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Completion Status -->
                                    <div class="mt-4 p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-semibold text-gray-700 dark:text-gray-200">Kelengkapan Data</span>
                                            <span class="text-xs font-bold" 
                                                  :class="completionPercent === 100 ? 'text-green-500' : (completionPercent >= 50 ? 'text-amber-500' : 'text-red-500')"
                                                  x-text="completionPercent + '%'"></span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-600 rounded-full h-2 overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-500"
                                                 :class="completionPercent === 100 ? 'bg-green-500' : (completionPercent >= 50 ? 'bg-amber-500' : 'bg-red-500')"
                                                 :style="'width: ' + completionPercent + '%'"></div>
                                        </div>
                                        <div class="mt-2 space-y-1">
                                            <template x-for="item in completionItems">
                                                <div class="flex items-center gap-2 text-xs">
                                                    <i :class="item.done ? 'fa-check-circle text-green-500' : 'fa-circle text-gray-300'" class="fas text-[10px]"></i>
                                                    <span :class="item.done ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400'" x-text="item.label"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                    
                                    <!-- Quick Tips -->
                                    <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                                        <p class="text-xs font-semibold text-blue-900 dark:text-blue-300 mb-2">
                                            <i class="fas fa-lightbulb mr-1"></i>
                                            Tips
                                        </p>
                                        <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1">
                                            <li>• Kode ruangan harus unik</li>
                                            <li>• Isi minimal data wajib (*)</li>
                                            <li>• Preview akan update otomatis</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- Toast Container -->
<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function formApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        isSubmitting: false,
        form: {
            kode_ruangan: '',
            nama_ruangan: '',
            gedung: '',
            lantai: '',
            kapasitas: 30,
            fungsi_ruangan: '',
            penanggung_jawab: '',
            keterangan: ''
        },
        validation: {
            kode: { status: '', message: '' }
        },
        
        get completionItems() {
            return [
                { label: 'Kode Ruangan', done: this.form.kode_ruangan.length > 0 },
                { label: 'Nama Ruangan', done: this.form.nama_ruangan.length > 0 },
                { label: 'Gedung', done: this.form.gedung.length > 0 },
                { label: 'Lantai', done: this.form.lantai !== '' },
                { label: 'Fungsi Ruangan', done: this.form.fungsi_ruangan.length > 0 }
            ];
        },
        
        get completionPercent() {
            const done = this.completionItems.filter(i => i.done).length;
            return Math.round((done / this.completionItems.length) * 100);
        },
        
        init() {
            // Load draft from localStorage
            const draft = localStorage.getItem('room_draft');
            if (draft) {
                try {
                    const data = JSON.parse(draft);
                    Object.assign(this.form, data);
                } catch(e) {}
            }
            
            // Auto-save draft every 2 seconds
            setInterval(() => {
                if (Object.values(this.form).some(v => v !== '' && v !== 30)) {
                    localStorage.setItem('room_draft', JSON.stringify(this.form));
                }
            }, 2000);
            
            // Keyboard shortcut: Ctrl+Enter to submit
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    e.preventDefault();
                    this.$refs.roomForm.requestSubmit();
                }
            });
        },
        
        validateKode() {
            const kode = this.form.kode_ruangan.trim();
            if (!kode) {
                this.validation.kode = { status: '', message: '' };
                return;
            }
            if (kode.length < 3) {
                this.validation.kode = { 
                    status: 'border-red-500', 
                    message: 'Kode minimal 3 karakter' 
                };
            } else if (!/^[A-Z0-9-]+$/.test(kode)) {
                this.validation.kode = { 
                    status: 'border-red-500', 
                    message: 'Hanya huruf kapital, angka, dan dash (-)' 
                };
            } else {
                this.validation.kode = { 
                    status: 'border-green-500', 
                    message: 'Kode valid ✓' 
                };
            }
        },
        
        resetForm() {
            Swal.fire({
                title: 'Reset Form?',
                text: 'Semua data yang sudah diisi akan dihapus',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d69e2e',
                cancelButtonColor: '#718096',
                confirmButtonText: 'Ya, Reset',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    this.form = {
                        kode_ruangan: '',
                        nama_ruangan: '',
                        gedung: '',
                        lantai: '',
                        kapasitas: 30,
                        fungsi_ruangan: '',
                        penanggung_jawab: '',
                        keterangan: ''
                    };
                    this.validation = { kode: { status: '', message: '' } };
                    localStorage.removeItem('room_draft');
                    this.showToast('Form berhasil direset', 'info');
                }
            });
        },
        
        handleSubmit(event) {
            // Validate required fields
            if (!this.form.kode_ruangan || !this.form.nama_ruangan || !this.form.gedung || !this.form.lantai) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Data Belum Lengkap',
                    text: 'Mohon lengkapi semua field yang wajib diisi (*)',
                    confirmButtonColor: '#1a365d'
                });
                return;
            }
            
            if (this.validation.kode.status === 'border-red-500') {
                event.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Kode Ruangan Tidak Valid',
                    text: this.validation.kode.message,
                    confirmButtonColor: '#1a365d'
                });
                return;
            }
            
            // Show loading
            this.isSubmitting = true;
            
            // Clear draft on successful submit
            localStorage.removeItem('room_draft');
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
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
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
</script>

</body>
</html>