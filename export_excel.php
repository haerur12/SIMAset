<?php
require 'config.php';
// Load Composer autoload (needed for PDF export)
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$current_page = basename($_SERVER['PHP_SELF']);
$download = isset($_GET['download']) && $_GET['download'] == '1';
$download_pdf = isset($_GET['download_pdf']) && $_GET['download_pdf'] == '1';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ============================================
// ✅ STATISTIK BREAKDOWN (SAMA SEPERTI DASHBOARD)
// ============================================

// Total unit semua aset (SUM jumlah, bukan COUNT)
$total_unit = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris")->fetch_assoc()['total'];
$total_record = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];

// Total unit per sumber pengadaan (SUM jumlah)
$total_pemerintah = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'Pemerintah'")->fetch_assoc()['total'];
$total_sekolah = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'Sekolah'")->fetch_assoc()['total'];
$total_bos = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'BOS'")->fetch_assoc()['total'];
$total_dak = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'DAK'")->fetch_assoc()['total'];
$total_apbd = mysqli_query($conn, "SELECT COALESCE(SUM(jumlah), 0) as total FROM inventaris WHERE sumber_pengadaan = 'APBD'")->fetch_assoc()['total'];

// Total nilai
$total_nilai = mysqli_query($conn, "SELECT COALESCE(SUM(total), 0) as total FROM inventaris")->fetch_assoc()['total'];

// Rata-rata nilai per unit
$rata_rata_nilai = $total_unit > 0 ? round($total_nilai / $total_unit) : 0;

// Persentase per sumber
$persen_pemerintah = $total_unit > 0 ? round(($total_pemerintah / $total_unit) * 100) : 0;
$persen_sekolah = $total_unit > 0 ? round(($total_sekolah / $total_unit) * 100) : 0;
$persen_bos = $total_unit > 0 ? round(($total_bos / $total_unit) * 100) : 0;
$persen_dak = $total_unit > 0 ? round(($total_dak / $total_unit) * 100) : 0;
$persen_apbd = $total_unit > 0 ? round(($total_apbd / $total_unit) * 100) : 0;

// Jumlah sumber yang aktif
$sumber_aktif = 0;
if($total_pemerintah > 0) $sumber_aktif++;
if($total_sekolah > 0) $sumber_aktif++;
if($total_bos > 0) $sumber_aktif++;
if($total_dak > 0) $sumber_aktif++;
if($total_apbd > 0) $sumber_aktif++;

// Search untuk Preview
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = "";
if($search && !$download && !$download_pdf) {
    $where = "WHERE spesifikasi_nama_barang LIKE '%$search%' OR nama_barang_108 LIKE '%$search%'";
}

// Query Data
$result = mysqli_query($conn, "SELECT * FROM inventaris $where ORDER BY created_at DESC");
$total_records = mysqli_num_rows($result);

// Hitung estimasi ukuran file
$estimated_size_excel = ($total_records * 2.5) + 15;
$estimated_size_pdf = ($total_records * 3.5) + 50;

// ============================================
// ✅ EXPORT PDF MODE
// ============================================
if($download_pdf) {
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $dompdf = new Dompdf($options);
    
    $grand_total = 0;
    $data_rows = [];
    mysqli_data_seek($result, 0);
    while($row = mysqli_fetch_assoc($result)) {
        $grand_total += $row['total'];
        $data_rows[] = $row;
    }
    
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 1.5cm 1cm; size: A4 landscape; }
        body { font-family: Arial, sans-serif; font-size: 10px; line-height: 1.3; color: #333; }
        .header { text-align: center; margin-bottom: 15px; border-bottom: 3px solid #1a365d; padding-bottom: 10px; }
        .header h1 { margin: 0 0 3px 0; font-size: 16px; color: #1a365d; text-transform: uppercase; }
        .header h2 { margin: 0 0 3px 0; font-size: 13px; color: #2c5282; }
        .header p { margin: 2px 0; font-size: 9px; color: #666; }
        .stats-box { background-color: #f0f4f8; padding: 10px; border-radius: 4px; margin-bottom: 10px; border-left: 3px solid #1a365d; font-size: 9px; }
        .stats-box strong { color: #1a365d; }
        .stats-grid { display: table; width: 100%; margin-top: 8px; }
        .stats-row { display: table-row; }
        .stats-cell { display: table-cell; padding: 5px; text-align: center; border: 1px solid #ddd; background: white; }
        .stats-cell .label { font-size: 8px; color: #666; text-transform: uppercase; }
        .stats-cell .value { font-size: 14px; font-weight: bold; color: #1a365d; }
        table { width: 100%; border-collapse: collapse; font-size: 8px; }
        table th { background-color: #1a365d; color: white; padding: 5px 3px; text-align: left; font-size: 8px; border: 1px solid #0f2744; }
        table td { padding: 4px 3px; border: 1px solid #ddd; vertical-align: top; }
        table tr:nth-child(even) td { background-color: #f8fafc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .wrap { word-wrap: break-word; max-width: 120px; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 2px; font-size: 7px; font-weight: bold; color: white; }
        .badge-pemerintah { background-color: #1a365d; }
        .badge-sekolah { background-color: #d69e2e; }
        .badge-bos { background-color: #38a169; }
        .badge-dak { background-color: #3182ce; }
        .badge-apbd { background-color: #e53e3e; }
        .grand-total { font-weight: bold; background-color: #e2e8f0 !important; font-size: 9px; }
        .footer { margin-top: 20px; text-align: right; font-size: 9px; }
        .signature-box { display: inline-block; text-align: center; min-width: 180px; margin-top: 30px; }
        .signature-line { border-top: 1px solid #333; margin-top: 40px; padding-top: 3px; }
        .watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 70px; color: rgba(26, 54, 93, 0.04); z-index: -1; font-weight: bold; }
    </style>
</head>
<body>
    <div class="watermark">INVENTARIS</div>
    
    <div class="header">
        <h1>SDN CURUG 01</h1>
        <h2>Laporan Inventaris Aset Sekolah</h2>
        <p>Bojongsari | Tahun ' . date('Y') . '</p>
        <p>Dicetak pada: ' . date("d F Y, H:i") . ' WIB</p>
    </div>
    
    <div class="stats-box">
        <strong>📊 RINGKASAN DATA INVENTARIS</strong>
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stats-cell">
                    <div class="label">Total Unit</div>
                    <div class="value">' . number_format($total_unit) . '</div>
                </div>
                <div class="stats-cell">
                    <div class="label">Total Jenis</div>
                    <div class="value">' . number_format($total_record) . '</div>
                </div>
                <div class="stats-cell">
                    <div class="label">Total Nilai</div>
                    <div class="value" style="font-size: 11px;">' . formatRupiah($total_nilai) . '</div>
                </div>
                <div class="stats-cell">
                    <div class="label">Rata-rata/Unit</div>
                    <div class="value" style="font-size: 11px;">' . formatRupiah($rata_rata_nilai) . '</div>
                </div>
                <div class="stats-cell">
                    <div class="label">Sumber Aktif</div>
                    <div class="value">' . $sumber_aktif . '/5</div>
                </div>
            </div>
        </div>
        <div style="margin-top: 8px; font-size: 8px;">
            <strong>Breakdown per Sumber:</strong> 
            Pemerintah: ' . number_format($total_pemerintah) . ' unit (' . $persen_pemerintah . '%) | 
            Sekolah: ' . number_format($total_sekolah) . ' unit (' . $persen_sekolah . '%) | 
            BOS: ' . number_format($total_bos) . ' unit (' . $persen_bos . '%) | 
            DAK: ' . number_format($total_dak) . ' unit (' . $persen_dak . '%) | 
            APBD: ' . number_format($total_apbd) . ' unit (' . $persen_apbd . '%)
        </div>
        <div style="margin-top: 5px;">
            <strong>Filter:</strong> ' . ($search ? '"' . $search . '"' : 'Semua data') . '
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th width="3%">No</th>
                <th width="8%">Sumber</th>
                <th width="7%">Kode Lokasi</th>
                <th width="10%">Nama Unit</th>
                <th width="7%">Kode Barang</th>
                <th width="13%">Nama Barang</th>
                <th width="13%">Spesifikasi</th>
                <th width="5%">Satuan</th>
                <th width="4%">Jumlah</th>
                <th width="8%">Harga Satuan</th>
                <th width="9%">Total</th>
                <th>No Kontrak</th>
                <th>Tgl Kontrak</th>
                <th>No BAST</th>
                <th>Tgl BAST</th>
                <th>PPK</th>
                <th>Pengurus</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>';
    
    $no = 1;
    foreach($data_rows as $row) {
        $badge_class = 'badge-pemerintah';
        switch($row['sumber_pengadaan']) {
            case 'Sekolah': $badge_class = 'badge-sekolah'; break;
            case 'BOS': $badge_class = 'badge-bos'; break;
            case 'DAK': $badge_class = 'badge-dak'; break;
            case 'APBD': $badge_class = 'badge-apbd'; break;
        }
        
        $html .= '
            <tr>
                <td class="text-center">' . $no++ . '</td>
                <td><span class="badge ' . $badge_class . '">' . htmlspecialchars($row['sumber_pengadaan']) . '</span></td>
                <td>' . htmlspecialchars($row['kode_lokasi']) . '</td>
                <td class="wrap">' . htmlspecialchars($row['nama_unit_lokasi']) . '</td>
                <td class="text-center">' . htmlspecialchars($row['kode_barang_108']) . '</td>
                <td class="wrap"><strong>' . htmlspecialchars($row['nama_barang_108']) . '</strong></td>
                <td class="wrap">' . htmlspecialchars($row['spesifikasi_nama_barang']) . '</td>
                <td class="text-center">' . htmlspecialchars($row['satuan']) . '</td>
                <td class="text-center">' . $row['jumlah'] . '</td>
                <td class="text-right">Rp ' . number_format($row['harga_satuan'], 0, ',', '.') . '</td>
                <td class="text-right"><strong>Rp ' . number_format($row['total'], 0, ',', '.') . '</strong></td>
                <td>' . htmlspecialchars($row['no_dokumen_kontrak']) . '</td>
                <td class="text-center">' . ($row['tanggal_kontrak'] ? date('d/m/Y', strtotime($row['tanggal_kontrak'])) : '-') . '</td>
                <td>' . htmlspecialchars($row['no_bast']) . '</td>
                <td class="text-center">' . ($row['tanggal_bast'] ? date('d/m/Y', strtotime($row['tanggal_bast'])) : '-') . '</td>
                <td class="wrap">' . htmlspecialchars($row['nama_ppk']) . '</td>
                <td class="wrap">' . htmlspecialchars($row['nama_pengurus_barang']) . '</td>
                <td class="wrap">' . htmlspecialchars($row['keterangan']) . '</td>
            </tr>';
    }
    
    $html .= '
            <tr class="grand-total">
                <td colspan="10" class="text-right">GRAND TOTAL:</td>
                <td class="text-right">Rp ' . number_format($grand_total, 0, ',', '.') . '</td>
                <td colspan="6"></td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <div class="signature-box">
            <p>Mengetahui,</p>
            <p>Kepala Sekolah SDN Curug 01</p>
            <div class="signature-line">
                <p><strong>(_________________)</strong></p>
                <p>NIP. -</p>
            </div>
        </div>
    </div>
</body>
</html>';
    
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    $filename = "Laporan_Inventaris_SDN_Curug01_" . date("Y-m-d") . ($search ? "_" . preg_replace('/[^a-zA-Z0-9]/', '_', $search) : "") . ".pdf";
    $dompdf->stream($filename, array("Attachment" => true));
    exit;
}

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
                    colors: { primary: { DEFAULT: '#1a365d', dark: '#0f2744', light: '#2c5282' } },
                    fontFamily: { sans: ['Segoe UI', 'Tahoma', 'Geneva', 'Verdana', 'sans-serif'] },
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
        .stagger-item:nth-child(1) { animation-delay: 0.05s; }
        .stagger-item:nth-child(2) { animation-delay: 0.1s; }
        .stagger-item:nth-child(3) { animation-delay: 0.15s; }
        .stagger-item:nth-child(4) { animation-delay: 0.2s; }
        .stagger-item:nth-child(5) { animation-delay: 0.25s; }
        
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
        .dark .excel-table tbody td { border-color: #4a5568; }
        .excel-table tbody tr:nth-child(even) { background-color: #f7fafc; }
        .dark .excel-table tbody tr:nth-child(even) { background-color: rgba(45, 55, 72, 0.5); }
        .excel-table tbody tr:hover { background-color: #edf2f7 !important; }
        .dark .excel-table tbody tr:hover { background-color: rgba(74, 85, 104, 0.5) !important; }
        
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
        .excel-badge-dak { background-color: #3182ce; }
        .excel-badge-apbd { background-color: #e53e3e; }
        
        .grand-total-row {
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 100%) !important;
            font-weight: 700;
        }
        .dark .grand-total-row {
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%) !important;
        }
        
        .excel-doc-header {
            background: linear-gradient(135deg, #1a365d 0%, #2c5282 100%);
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        
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
        .download-btn:hover::before { left: 100%; }
    </style>
</head>

<body class="bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-200 font-sans">

<div class="flex min-h-screen" x-data="exportApp()">
    
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
                        <span class="text-primary font-semibold">Export Data</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-file-export text-primary"></i>
                        <span>Export Data Inventaris</span>
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
        
        <div class="flex-1 p-4 lg:p-8 space-y-6 animate-fade-in">
            
            <!-- Hero Download Section -->
            <div class="bg-gradient-to-br from-primary via-primary-light to-primary rounded-2xl shadow-xl p-6 lg:p-8 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="absolute bottom-0 left-0 w-48 h-48 bg-white/5 rounded-full translate-y-24 -translate-x-24"></div>
                <div class="absolute top-1/2 right-10 hidden lg:block opacity-10">
                    <i class="fas fa-file-export text-[180px]"></i>
                </div>
                
                <div class="relative">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-3 bg-white/20 backdrop-blur rounded-xl animate-bounce-soft">
                            <i class="fas fa-file-export text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-2xl lg:text-3xl font-bold">Export Data Inventaris</h3>
                            <p class="text-sm text-white/90">Download data dalam format Excel atau PDF</p>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-3 mt-4 mb-6">
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                            <i class="fas fa-cubes"></i>
                            <span><strong><?= number_format($total_unit) ?></strong> unit</span>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur rounded-lg text-xs">
                            <i class="fas fa-database"></i>
                            <span><strong><?= number_format($total_record) ?></strong> jenis</span>
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
                    
                    <!-- DUAL DOWNLOAD BUTTONS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <button @click="confirmDownloadExcel()" 
                                class="download-btn w-full px-6 py-4 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl shadow-lg hover:shadow-2xl transition-all flex items-center justify-center gap-3 font-bold text-base group">
                            <i class="fas fa-file-excel text-2xl group-hover:animate-bounce"></i>
                            <div class="text-left">
                                <div>Download Excel</div>
                                <div class="text-xs font-normal opacity-80">Format .xls • ~<?= number_format($estimated_size_excel, 1) ?> KB</div>
                            </div>
                        </button>
                        
                        <button @click="confirmDownloadPDF()" 
                                class="download-btn w-full px-6 py-4 bg-red-500 hover:bg-red-600 text-white rounded-xl shadow-lg hover:shadow-2xl transition-all flex items-center justify-center gap-3 font-bold text-base group">
                            <i class="fas fa-file-pdf text-2xl group-hover:animate-bounce"></i>
                            <div class="text-left">
                                <div>Download PDF</div>
                                <div class="text-xs font-normal opacity-80">Format .pdf • ~<?= number_format($estimated_size_pdf, 1) ?> KB</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- ✅ STATISTICS CARDS - BREAKDOWN LENGKAP (5 CARDS) -->
            <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                <?php 
                $stats = [
                    ['Total Unit', number_format($total_unit), 'fa-cubes', 'from-blue-500 to-blue-600', $total_record . ' jenis aset'],
                    ['Pemerintah', number_format($total_pemerintah), 'fa-landmark', 'from-primary to-primary-dark', $persen_pemerintah . '% dari total'],
                    ['Sekolah', number_format($total_sekolah), 'fa-school', 'from-amber-500 to-amber-600', $persen_sekolah . '% dari total'],
                    ['BOS', number_format($total_bos), 'fa-money-bill-wave', 'from-emerald-500 to-emerald-600', $persen_bos . '% dari total'],
                    ['Total Nilai', formatRupiah($total_nilai), 'fa-coins', 'from-purple-500 to-purple-600', 'Rata-rata: ' . formatRupiah($rata_rata_nilai) . '/unit'],
                ];
                foreach($stats as $idx => $stat): ?>
                <div class="stagger-item group relative bg-white dark:bg-gray-800 rounded-xl shadow-md hover:shadow-2xl transition-all duration-300 overflow-hidden border border-gray-100 dark:border-gray-700 hover:-translate-y-1">
                    <div class="absolute inset-0 bg-gradient-to-br <?= $stat[3] ?> opacity-0 group-hover:opacity-5 transition-opacity"></div>
                    <div class="relative p-4 lg:p-5">
                        <div class="flex items-start justify-between mb-2">
                            <div class="p-2.5 rounded-lg bg-gradient-to-br <?= $stat[3] ?> text-white shadow-lg">
                                <i class="fas <?= $stat[2] ?> text-base"></i>
                            </div>
                        </div>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500 dark:text-gray-400 font-semibold mb-1"><?= $stat[0] ?></p>
                        <h3 class="text-xl lg:text-2xl font-bold text-gray-800 dark:text-white truncate"><?= $stat[1] ?></h3>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-1"><?= $stat[4] ?></p>
                    </div>
                    <div class="h-1 bg-gradient-to-r <?= $stat[3] ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- ✅ BREAKDOWN SUMBER PENGADAAN (Detail) -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <i class="fas fa-chart-pie text-primary text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-gray-800 dark:text-white">Breakdown Sumber Pengadaan</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Distribusi unit aset berdasarkan sumber pengadaan</p>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        <?php 
                        $sumber_cards = [
                            ['Pemerintah', $total_pemerintah, $persen_pemerintah, 'fa-landmark', 'from-primary to-primary-dark', 'bg-primary/10', 'text-primary'],
                            ['Sekolah', $total_sekolah, $persen_sekolah, 'fa-school', 'from-amber-500 to-amber-600', 'bg-amber-100', 'text-amber-700'],
                            ['BOS', $total_bos, $persen_bos, 'fa-money-bill-wave', 'from-emerald-500 to-emerald-600', 'bg-emerald-100', 'text-emerald-700'],
                            ['DAK', $total_dak, $persen_dak, 'fa-building-columns', 'from-blue-500 to-cyan-600', 'bg-blue-100', 'text-blue-700'],
                            ['APBD', $total_apbd, $persen_apbd, 'fa-landmark-flag', 'from-rose-500 to-pink-600', 'bg-rose-100', 'text-rose-700'],
                        ];
                        foreach($sumber_cards as $card): ?>
                        <div class="relative bg-gradient-to-br <?= $card[5] ?> dark:bg-gray-700/50 rounded-lg p-3 border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center justify-between mb-2">
                                <div class="p-1.5 bg-gradient-to-br <?= $card[4] ?> rounded text-white">
                                    <i class="fas <?= $card[3] ?> text-xs"></i>
                                </div>
                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-white dark:bg-gray-800 rounded <?= $card[6] ?>">
                                    <?= $card[2] ?>%
                                </span>
                            </div>
                            <p class="text-[10px] uppercase tracking-wider text-gray-600 dark:text-gray-400 font-semibold"><?= $card[0] ?></p>
                            <p class="text-lg font-bold text-gray-800 dark:text-white"><?= number_format($card[1]) ?></p>
                            <p class="text-[9px] text-gray-500 dark:text-gray-400">unit aset</p>
                            
                            <!-- Mini Progress Bar -->
                            <div class="mt-2 w-full bg-gray-200 dark:bg-gray-600 rounded-full h-1 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r <?= $card[4] ?>" style="width: <?= $card[2] ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                                <span><strong>Excel:</strong> Format .xls, kompatibel Excel 2007+</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span><strong>PDF:</strong> Format A4 Landscape, siap cetak</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Data terupdate hingga hari ini</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-check text-blue-500 mt-0.5"></i>
                                <span>Dilengkapi ringkasan & grand total</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Preview Table Card -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                
                <div class="p-5 lg:p-6 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-primary/10 rounded-lg">
                                <i class="fas fa-eye text-primary text-lg"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Preview Data</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    Tampilan yang akan diexport (Excel & PDF)
                                    <?php if($search): ?>
                                        • Filter: <strong class="text-primary">"<?= htmlspecialchars($search) ?>"</strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <form method="GET" class="flex gap-2 w-full lg:w-auto">
                            <input type="hidden" name="download" value="0">
                            <div class="relative flex-1 lg:w-80">
                                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                <input type="text" name="search" 
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
                        <span><i class="fas fa-cubes mr-1"></i> Total: <strong><?= number_format($total_unit) ?> unit</strong></span>
                        <span><i class="fas fa-database mr-1"></i> Jenis: <strong><?= number_format($total_record) ?></strong></span>
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
                                $badgeClass = 'excel-badge-pemerintah';
                                switch($row['sumber_pengadaan']) {
                                    case 'Pemerintah': $badgeClass = 'excel-badge-pemerintah'; break;
                                    case 'Sekolah': $badgeClass = 'excel-badge-sekolah'; break;
                                    case 'BOS': $badgeClass = 'excel-badge-bos'; break;
                                    case 'DAK': $badgeClass = 'excel-badge-dak'; break;
                                    case 'APBD': $badgeClass = 'excel-badge-apbd'; break;
                                }
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
                        <div class="flex items-center gap-2">
                            <button @click="confirmDownloadExcel()" 
                                    class="px-4 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg transition-all flex items-center gap-2 text-xs font-semibold">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button @click="confirmDownloadPDF()" 
                                    class="px-4 py-2 bg-red-500 hover:bg-red-600 text-white rounded-lg transition-all flex items-center gap-2 text-xs font-semibold">
                                <i class="fas fa-file-pdf"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </main>
</div>

<div id="toast-container" class="fixed top-4 right-4 z-[100] space-y-2"></div>

<script>
function exportApp() {
    return {
        darkMode: localStorage.getItem('darkMode') === 'true',
        sidebarOpen: false,
        
        init() {
            setTimeout(() => {
                this.showToast('Preview siap! Pilih format untuk download', 'info');
            }, 1000);
        },
        
        confirmDownloadExcel() {
            const totalRecords = <?= $total_records ?>;
            const estimatedSize = <?= $estimated_size_excel ?>;
            const searchFilter = '<?= addslashes($search) ?>';
            
            if (totalRecords === 0) {
                Swal.fire({ icon: 'warning', title: 'Tidak Ada Data', text: 'Tidak ada data yang bisa diexport.', confirmButtonColor: '#1a365d' });
                return;
            }
            
            Swal.fire({
                title: '<i class="fas fa-file-excel text-emerald-500 mr-2"></i> Download Excel',
                html: `
                    <div class="text-left space-y-3 mt-4">
                        <div class="p-4 bg-gradient-to-br from-emerald-50 to-green-50 dark:from-emerald-900/20 dark:to-green-900/20 rounded-lg border border-emerald-200 dark:border-emerald-800">
                            <p class="text-xs font-semibold text-emerald-900 dark:text-emerald-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i> Detail File Excel
                            </p>
                            <div class="space-y-1.5 text-xs text-emerald-800 dark:text-emerald-400">
                                <div class="flex justify-between"><span><i class="fas fa-file-excel mr-2"></i> Format</span><strong>Microsoft Excel (.xls)</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-database mr-2"></i> Jumlah Data</span><strong>${totalRecords.toLocaleString('id-ID')} baris</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-weight-hanging mr-2"></i> Estimasi Ukuran</span><strong>~${estimatedSize.toFixed(1)} KB</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-table-columns mr-2"></i> Kolom</span><strong>18 kolom</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-calendar mr-2"></i> Tanggal Export</span><strong>${new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</strong></div>
                                ${searchFilter ? `<div class="flex justify-between pt-1.5 border-t border-emerald-200 dark:border-emerald-800"><span><i class="fas fa-filter mr-2"></i> Filter</span><strong class="truncate ml-2">"${searchFilter}"</strong></div>` : ''}
                            </div>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#718096',
                confirmButtonText: '<i class="fas fa-download mr-1"></i> Download Excel!',
                cancelButtonText: 'Batal',
                width: '500px'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mempersiapkan File Excel...',
                        html: '<div class="flex flex-col items-center gap-3 mt-3"><i class="fas fa-file-excel text-5xl text-emerald-500 animate-bounce"></i><p class="text-sm text-gray-600">Mohon tunggu...</p></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        timer: 1500,
                        timerProgressBar: true
                    }).then(() => {
                        const searchParam = searchFilter ? `&search=${encodeURIComponent(searchFilter)}` : '';
                        window.location.href = `export_excel.php?download=1${searchParam}`;
                        setTimeout(() => this.showToast('File Excel berhasil didownload!', 'success'), 1000);
                    });
                }
            });
        },
        
        confirmDownloadPDF() {
            const totalRecords = <?= $total_records ?>;
            const estimatedSize = <?= $estimated_size_pdf ?>;
            const searchFilter = '<?= addslashes($search) ?>';
            
            if (totalRecords === 0) {
                Swal.fire({ icon: 'warning', title: 'Tidak Ada Data', text: 'Tidak ada data yang bisa diexport.', confirmButtonColor: '#1a365d' });
                return;
            }
            
            Swal.fire({
                title: '<i class="fas fa-file-pdf text-red-500 mr-2"></i> Download PDF',
                html: `
                    <div class="text-left space-y-3 mt-4">
                        <div class="p-4 bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-lg border border-red-200 dark:border-red-800">
                            <p class="text-xs font-semibold text-red-900 dark:text-red-300 mb-2 flex items-center gap-2">
                                <i class="fas fa-info-circle"></i> Detail File PDF
                            </p>
                            <div class="space-y-1.5 text-xs text-red-800 dark:text-red-400">
                                <div class="flex justify-between"><span><i class="fas fa-file-pdf mr-2"></i> Format</span><strong>PDF Document (.pdf)</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-ruler-combined mr-2"></i> Ukuran Kertas</span><strong>A4 Landscape</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-database mr-2"></i> Jumlah Data</span><strong>${totalRecords.toLocaleString('id-ID')} baris</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-weight-hanging mr-2"></i> Estimasi Ukuran</span><strong>~${estimatedSize.toFixed(1)} KB</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-table-columns mr-2"></i> Kolom</span><strong>18 kolom</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-calendar mr-2"></i> Tanggal Export</span><strong>${new Date().toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })}</strong></div>
                                <div class="flex justify-between"><span><i class="fas fa-signature mr-2"></i> Tanda Tangan</span><strong>Ya (Kepala Sekolah)</strong></div>
                                ${searchFilter ? `<div class="flex justify-between pt-1.5 border-t border-red-200 dark:border-red-800"><span><i class="fas fa-filter mr-2"></i> Filter</span><strong class="truncate ml-2">"${searchFilter}"</strong></div>` : ''}
                            </div>
                        </div>
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800">
                            <p class="text-xs text-blue-800 dark:text-blue-300">
                                <i class="fas fa-lightbulb text-blue-500 mr-1"></i>
                                <strong>Tips:</strong> PDF ini siap cetak dengan header sekolah & tanda tangan Kepala Sekolah.
                            </p>
                        </div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#718096',
                confirmButtonText: '<i class="fas fa-download mr-1"></i> Download PDF!',
                cancelButtonText: 'Batal',
                width: '500px'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Mempersiapkan File PDF...',
                        html: '<div class="flex flex-col items-center gap-3 mt-3"><i class="fas fa-file-pdf text-5xl text-red-500 animate-bounce"></i><p class="text-sm text-gray-600">Mohon tunggu sebentar...</p></div>',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        timer: 2500,
                        timerProgressBar: true
                    }).then(() => {
                        const searchParam = searchFilter ? `&search=${encodeURIComponent(searchFilter)}` : '';
                        window.location.href = `export_excel.php?download_pdf=1${searchParam}`;
                        setTimeout(() => this.showToast('File PDF berhasil didownload!', 'success'), 1000);
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

document.addEventListener('keydown', (e) => {
    const app = document.querySelector('[x-data]').__x;
    if (!app) return;
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        app.$data.confirmDownloadExcel();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
        e.preventDefault();
        app.$data.confirmDownloadPDF();
    }
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
<!-- EXCEL OUTPUT MODE -->
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
        .excel-header { text-align: center; padding: 15px; background: #1a365d; color: white; font-family: Arial, sans-serif; }
        .excel-header h3 { margin: 0 0 5px 0; font-size: 18px; }
        .excel-header p { margin: 0; font-size: 12px; }
        table.excel { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; font-size: 11px; }
        table.excel th { background-color: #1a365d; color: white; padding: 8px; font-weight: bold; border: 1px solid #0f2744; text-align: center; }
        table.excel td { padding: 6px 8px; border: 1px solid #cbd5e0; vertical-align: middle; }
        table.excel tr:nth-child(even) td { background-color: #f7fafc; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .wrap { white-space: normal; word-wrap: break-word; max-width: 180px; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 10px; font-weight: bold; color: white; }
        .badge-pemerintah { background-color: #1a365d; }
        .badge-sekolah { background-color: #d69e2e; }
        .badge-bos { background-color: #38a169; }
        .badge-dak { background-color: #3182ce; }
        .badge-apbd { background-color: #e53e3e; }
        .grand-total { font-weight: bold; background-color: #e2e8f0 !important; font-size: 12px; }
        .summary-box { background: #f0f4f8; padding: 10px; border-left: 4px solid #1a365d; margin: 10px 0; font-size: 11px; }
    </style>
</head>
<body>
    <div class="excel-header">
        <h3>DATA INVENTARIS SEKOLAH - SDN CURUG 01</h3>
        <p>Tanggal Export: <?= date('d F Y') ?> <?= $search ? '| Filter: "'.$search.'"' : '' ?></p>
    </div>
    
    <div class="summary-box">
        <strong>📊 RINGKASAN:</strong> 
        Total <strong><?= number_format($total_unit) ?> unit</strong> (<?= number_format($total_record) ?> jenis) | 
        Total Nilai: <strong><?= formatRupiah($total_nilai) ?></strong> | 
        Rata-rata: <strong><?= formatRupiah($rata_rata_nilai) ?>/unit</strong> |
        Sumber Aktif: <strong><?= $sumber_aktif ?>/5</strong>
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
                $badgeClass = 'badge-pemerintah';
                switch($row['sumber_pengadaan']) {
                    case 'Pemerintah': $badgeClass = 'badge-pemerintah'; break;
                    case 'Sekolah': $badgeClass = 'badge-sekolah'; break;
                    case 'BOS': $badgeClass = 'badge-bos'; break;
                    case 'DAK': $badgeClass = 'badge-dak'; break;
                    case 'APBD': $badgeClass = 'badge-apbd'; break;
                }
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