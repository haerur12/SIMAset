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
// ✅ PROSES: BUAT ARSIP BULANAN (MANUAL)
// ============================================
if(isset($_POST['buat_arsip_bulanan'])) {
    requireAccess('create', 'laporan_peminjaman.php');
    
    $bulan = intval($_POST['bulan']);
    $tahun = intval($_POST['tahun']);
    
    if($bulan < 1 || $bulan > 12) {
        $_SESSION['flash_error'] = 'Bulan tidak valid!';
        header("Location: laporan_peminjaman.php");
        exit;
    }
    
    if($tahun < 2020 || $tahun > date('Y') + 1) {
        $_SESSION['flash_error'] = 'Tahun tidak valid!';
        header("Location: laporan_peminjaman.php");
        exit;
    }
    
    $stats = mysqli_query($conn, "SELECT 
        COUNT(*) as total_peminjaman,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) as total_dikembalikan,
        SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat,
        COALESCE(SUM(jumlah), 0) as total_unit,
        COALESCE(SUM(kondisi_rusak_ringan_kembali + kondisi_rusak_berat_kembali + kondisi_perbaikan_kembali), 0) as total_rusak
        FROM peminjaman_aset 
        WHERE MONTH(tanggal_pinjam) = $bulan AND YEAR(tanggal_pinjam) = $tahun")->fetch_assoc();
    
    if($stats['total_peminjaman'] == 0) {
        $_SESSION['flash_error'] = "Tidak ada data peminjaman untuk " . nama_bulan_indo($bulan) . " $tahun!";
        header("Location: laporan_peminjaman.php");
        exit;
    }
    
    $detail = mysqli_query($conn, "SELECT 
        p.id AS peminjaman_id, p.peminjam, p.nip_peminjam, p.unit_kerja, p.no_hp,
        p.tanggal_pinjam, p.tanggal_kembali_rencana, p.tanggal_kembali_aktual, p.status, p.jumlah, p.keperluan,
        p.kondisi_baik_pinjam, p.kondisi_rusak_ringan_pinjam, p.kondisi_rusak_berat_pinjam, p.kondisi_perbaikan_pinjam,
        p.kondisi_baik_kembali, p.kondisi_rusak_ringan_kembali, p.kondisi_rusak_berat_kembali, p.kondisi_perbaikan_kembali,
        i.nama_barang_108 AS nama_barang_108
        FROM peminjaman_aset p
        LEFT JOIN inventaris i ON p.inventaris_id = i.id
        WHERE MONTH(p.tanggal_pinjam) = $bulan AND YEAR(p.tanggal_pinjam) = $tahun");
    
    $detail_array = [];
    while($d = mysqli_fetch_assoc($detail)) {
        $detail_array[] = $d;
    }
    
    $detail_json = mysqli_real_escape_string($conn, json_encode($detail_array));
    $dibuat_oleh = $_SESSION['user_id'] ?? null;
    
    $existing = mysqli_query($conn, "SELECT id FROM peminjaman_arsip_bulanan WHERE bulan = $bulan AND tahun = $tahun");
    
    if(mysqli_num_rows($existing) > 0) {
        $existing_id = mysqli_fetch_assoc($existing)['id'];
        mysqli_query($conn, "UPDATE peminjaman_arsip_bulanan SET 
            total_peminjaman = {$stats['total_peminjaman']},
            total_dikembalikan = {$stats['total_dikembalikan']},
            total_terlambat = {$stats['total_terlambat']},
            total_unit_dipinjam = {$stats['total_unit']},
            total_rusak_kembali = {$stats['total_rusak']},
            detail_json = '$detail_json',
            dibuat_oleh = $dibuat_oleh
            WHERE id = $existing_id");
        
        mysqli_query($conn, "UPDATE peminjaman_aset 
            SET diarsipkan = 1, bulan_arsip = $bulan, tahun_arsip = $tahun
            WHERE MONTH(tanggal_pinjam) = $bulan AND YEAR(tanggal_pinjam) = $tahun");
        
        update_status_notifikasi_arsip($bulan, $tahun, 'completed');
        $_SESSION['flash_success'] = "Arsip " . nama_bulan_indo($bulan) . " $tahun berhasil <strong>diupdate</strong>!";
    } else {
        mysqli_query($conn, "INSERT INTO peminjaman_arsip_bulanan 
            (bulan, tahun, total_peminjaman, total_dikembalikan, total_terlambat, 
             total_unit_dipinjam, total_rusak_kembali, detail_json, dibuat_oleh) 
            VALUES ($bulan, $tahun, {$stats['total_peminjaman']}, {$stats['total_dikembalikan']}, 
                    {$stats['total_terlambat']}, {$stats['total_unit']}, {$stats['total_rusak']}, 
                    '$detail_json', $dibuat_oleh)");
        
        mysqli_query($conn, "UPDATE peminjaman_aset 
            SET diarsipkan = 1, bulan_arsip = $bulan, tahun_arsip = $tahun
            WHERE MONTH(tanggal_pinjam) = $bulan AND YEAR(tanggal_pinjam) = $tahun");
        
        update_status_notifikasi_arsip($bulan, $tahun, 'completed');
        $_SESSION['flash_success'] = "Arsip " . nama_bulan_indo($bulan) . " $tahun berhasil <strong>dibuat</strong>!";
    }
    
    header("Location: laporan_peminjaman.php");
    exit;
}

// ============================================
// ✅ PROSES: HAPUS ARSIP
// ============================================
if(isset($_GET['hapus_arsip'])) {
    requireAccess('delete', 'laporan_peminjaman.php');
    $id = intval($_GET['hapus_arsip']);
    
    $arsip = mysqli_query($conn, "SELECT bulan, tahun FROM peminjaman_arsip_bulanan WHERE id = $id")->fetch_assoc();
    
    if($arsip) {
        if(mysqli_query($conn, "DELETE FROM peminjaman_arsip_bulanan WHERE id = $id")) {
            mysqli_query($conn, "UPDATE peminjaman_aset 
                SET diarsipkan = 0, bulan_arsip = NULL, tahun_arsip = NULL
                WHERE bulan_arsip = {$arsip['bulan']} AND tahun_arsip = {$arsip['tahun']}");
            update_status_notifikasi_arsip($arsip['bulan'], $arsip['tahun'], 'pending');
            $_SESSION['flash_success'] = 'Arsip berhasil dihapus!';
        }
    }
    header("Location: laporan_peminjaman.php");
    exit;
}

// ============================================
// ✅ PROSES: EXPORT ARSIP KE EXCEL
// ============================================
if(isset($_GET['export_arsip'])) {
    $id = intval($_GET['export_arsip']);
    $arsip = mysqli_query($conn, "SELECT * FROM peminjaman_arsip_bulanan WHERE id = $id")->fetch_assoc();
    
    if(!$arsip) die("Arsip tidak ditemukan");
    
    $bulan_nama = nama_bulan_indo($arsip['bulan']) . ' ' . $arsip['tahun'];
    
    header("Content-type: application/vnd-ms-excel");
    header("Content-Disposition: attachment; filename=Laporan_Peminjaman_" . str_replace(' ', '_', $bulan_nama) . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    echo '<html><head><meta charset="UTF-8"><style>
        table { border-collapse: collapse; width: 100%; font-family: Arial; font-size: 11px; }
        th { background: #1a365d; color: white; padding: 8px; border: 1px solid #0f2744; }
        td { padding: 6px; border: 1px solid #ccc; }
        .header { text-align: center; font-size: 14px; font-weight: bold; padding: 10px; background: #1a365d; color: white; }
        .subheader { text-align: center; font-size: 12px; padding: 5px; background: #e0e0e0; }
        .summary { background: #f0f0f0; font-weight: bold; }
    </style></head><body>';
    
    echo '<table>';
    echo '<tr><td colspan="13" class="header">LAPORAN PEMINJAMAN ASET - SDN CURUG 01</td></tr>';
    echo '<tr><td colspan="13" class="subheader">Periode: ' . $bulan_nama . '</td></tr>';
    echo '<tr><td colspan="13"></td></tr>';
    echo '<tr class="summary"><td colspan="13">RINGKASAN</td></tr>';
    echo '<tr><td colspan="4">Total Peminjaman</td><td colspan="9">' . $arsip['total_peminjaman'] . ' transaksi</td></tr>';
    echo '<tr><td colspan="4">Total Unit Dipinjam</td><td colspan="9">' . $arsip['total_unit_dipinjam'] . ' unit</td></tr>';
    echo '<tr><td colspan="4">Sudah Dikembalikan</td><td colspan="9">' . $arsip['total_dikembalikan'] . ' transaksi</td></tr>';
    echo '<tr><td colspan="4">Terlambat</td><td colspan="9">' . $arsip['total_terlambat'] . ' transaksi</td></tr>';
    echo '<tr><td colspan="4">Total Unit Rusak saat Kembali</td><td colspan="9">' . $arsip['total_rusak_kembali'] . ' unit</td></tr>';
    echo '<tr><td colspan="13"></td></tr>';
    echo '<tr class="summary"><td colspan="13">DETAIL PEMINJAMAN</td></tr>';
    echo '<tr><th>No</th><th>Tgl Pinjam</th><th>Peminjam</th><th>NIP</th><th>Unit Kerja</th><th>Nama Barang</th><th>Jumlah</th><th>Pinjam: Baik</th><th>Pinjam: RR</th><th>Pinjam: RB</th><th>Pinjam: Perbaikan</th><th>Status</th></tr>';
    
    $detail = json_decode($arsip['detail_json'], true);
    if(is_array($detail)) {
        $no = 1;
        foreach($detail as $d) {
            echo '<tr>';
            echo '<td>' . $no++ . '</td>';
            echo '<td>' . ($d['tanggal_pinjam'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($d['peminjam'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($d['nip_peminjam'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($d['unit_kerja'] ?? '-') . '</td>';
            echo '<td>' . htmlspecialchars($d['nama_barang_108'] ?? '-') . '</td>';
            echo '<td>' . ($d['jumlah'] ?? 0) . '</td>';
            echo '<td>' . ($d['kondisi_baik_pinjam'] ?? 0) . '</td>';
            echo '<td>' . ($d['kondisi_rusak_ringan_pinjam'] ?? 0) . '</td>';
            echo '<td>' . ($d['kondisi_rusak_berat_pinjam'] ?? 0) . '</td>';
            echo '<td>' . ($d['kondisi_perbaikan_pinjam'] ?? 0) . '</td>';
            echo '<td>' . ($d['status'] ?? '-') . '</td>';
            echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '<br><p style="text-align: right; font-size: 10px;">Dicetak: ' . date('d/m/Y H:i') . ' oleh ' . ($_SESSION['nama_lengkap'] ?? 'Admin') . '</p>';
    echo '</body></html>';
    exit;
}

// ============================================
// ✅ PROSES: EXPORT ARSIP KE PDF (BARU!)
// ============================================
if(isset($_GET['export_pdf'])) {
    $id = intval($_GET['export_pdf']);
    $arsip = mysqli_query($conn, "SELECT * FROM peminjaman_arsip_bulanan WHERE id = $id")->fetch_assoc();
    
    if(!$arsip) die("Arsip tidak ditemukan");
    
    $bulan_nama = nama_bulan_indo($arsip['bulan']) . ' ' . $arsip['tahun'];
    $detail = json_decode($arsip['detail_json'], true);
    
    // Cek apakah Dompdf tersedia
    $dompdf_available = file_exists(__DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php');
    
    if(!$dompdf_available) {
        // Fallback: Tampilkan halaman print-friendly
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Laporan Peminjaman - <?= $bulan_nama ?></title>
            <style>
                @page { size: A4 landscape; margin: 1.5cm; }
                body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
                .header { text-align: center; border-bottom: 3px double #1a365d; padding-bottom: 15px; margin-bottom: 20px; }
                .header h1 { margin: 0; font-size: 18px; color: #1a365d; }
                .header h2 { margin: 5px 0; font-size: 14px; color: #2c5282; }
                .header p { margin: 2px 0; font-size: 11px; color: #666; }
                .title { text-align: center; margin: 20px 0; padding: 10px; background: #1a365d; color: white; }
                .title h3 { margin: 0; font-size: 14px; }
                .title p { margin: 5px 0 0 0; font-size: 11px; }
                .summary-box { background: #f0f4f8; border: 1px solid #1a365d; padding: 15px; margin: 15px 0; border-radius: 5px; }
                .summary-box h4 { margin: 0 0 10px 0; color: #1a365d; border-bottom: 1px solid #1a365d; padding-bottom: 5px; }
                .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
                .summary-item { background: white; padding: 10px; border-radius: 4px; border-left: 3px solid #1a365d; }
                .summary-item .label { font-size: 10px; color: #666; text-transform: uppercase; }
                .summary-item .value { font-size: 18px; font-weight: bold; color: #1a365d; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 10px; }
                table th { background: #1a365d; color: white; padding: 8px 5px; border: 1px solid #0f2744; text-align: center; font-size: 10px; }
                table td { padding: 6px 5px; border: 1px solid #ccc; vertical-align: top; }
                table tr:nth-child(even) td { background: #f9f9f9; }
                .status-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 9px; font-weight: bold; }
                .status-dipinjam { background: #dbeafe; color: #1e40af; }
                .status-dikembalikan { background: #d1fae5; color: #065f46; }
                .status-terlambat { background: #fee2e2; color: #991b1b; }
                .signature { margin-top: 40px; }
                .signature-box { display: inline-block; width: 45%; text-align: center; vertical-align: top; }
                .signature-space { height: 70px; }
                .signature-name { border-top: 1px solid #333; padding-top: 5px; display: inline-block; min-width: 200px; }
                .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center; }
                .print-btn { position: fixed; top: 20px; right: 20px; padding: 12px 24px; background: #1a365d; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; }
                .print-btn:hover { background: #0f2744; }
                .info-box { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 10px; margin-bottom: 20px; font-size: 11px; }
                @media print {
                    .print-btn, .info-box { display: none !important; }
                    body { padding: 0; }
                }
            </style>
        </head>
        <body>
            <button class="print-btn" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
            
            <div class="info-box">
                <strong>ℹ️ Info:</strong> Untuk menyimpan sebagai PDF, klik tombol "Cetak" di atas, lalu pilih <strong>"Save as PDF"</strong> atau <strong>"Microsoft Print to PDF"</strong> sebagai printer tujuan.
            </div>
            
            <div class="header">
                <h1>PEMERINTAH DAERAH</h1>
                <h2>DINAS PENDIDIKAN</h2>
                <p><strong>UPTD SDN CURUG 01</strong></p>
                <p>Bojongsari</p>
            </div>
            
            <div class="title">
                <h3>LAPORAN BULANAN PEMINJAMAN ASET</h3>
                <p>Periode: <?= $bulan_nama ?></p>
            </div>
            
            <div class="summary-box">
                <h4>📊 RINGKASAN</h4>
                <div class="summary-grid">
                    <div class="summary-item">
                        <div class="label">Total Peminjaman</div>
                        <div class="value"><?= $arsip['total_peminjaman'] ?></div>
                        <div class="label">transaksi</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Total Unit Dipinjam</div>
                        <div class="value"><?= $arsip['total_unit_dipinjam'] ?></div>
                        <div class="label">unit</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Sudah Dikembalikan</div>
                        <div class="value"><?= $arsip['total_dikembalikan'] ?></div>
                        <div class="label">transaksi</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Terlambat</div>
                        <div class="value"><?= $arsip['total_terlambat'] ?></div>
                        <div class="label">transaksi</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Unit Rusak Kembali</div>
                        <div class="value"><?= $arsip['total_rusak_kembali'] ?></div>
                        <div class="label">unit</div>
                    </div>
                    <div class="summary-item">
                        <div class="label">Dibuat Oleh</div>
                        <div class="value" style="font-size: 12px;"><?= htmlspecialchars($arsip['dibuat_oleh_nama'] ?? 'Admin') ?></div>
                        <div class="label"><?= date('d/m/Y', strtotime($arsip['created_at'])) ?></div>
                    </div>
                </div>
            </div>
            
            <h4 style="color: #1a365d; border-bottom: 2px solid #1a365d; padding-bottom: 5px;">📋 DETAIL PEMINJAMAN</h4>
            
            <table>
                <thead>
                    <tr>
                        <th width="3%">No</th>
                        <th width="8%">Tgl Pinjam</th>
                        <th width="12%">Peminjam</th>
                        <th width="8%">NIP</th>
                        <th width="8%">Unit Kerja</th>
                        <th width="15%">Nama Barang</th>
                        <th width="5%">Jml</th>
                        <th width="5%">Baik</th>
                        <th width="5%">RR</th>
                        <th width="5%">RB</th>
                        <th width="5%">Perbaikan</th>
                        <th width="5%">Baik</th>
                        <th width="5%">RR</th>
                        <th width="5%">RB</th>
                        <th width="5%">Perbaikan</th>
                        <th width="8%">Status</th>
                    </tr>
                    <tr>
                        <th></th><th></th><th></th><th></th><th></th><th></th><th></th>
                        <th colspan="4" style="background: #2c5282;">Saat Pinjam</th>
                        <th colspan="4" style="background: #059669;">Saat Kembali</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(is_array($detail)): 
                        $no = 1;
                        foreach($detail as $d): 
                            $status_class = 'status-' . ($d['status'] ?? 'dipinjam');
                    ?>
                    <tr>
                        <td style="text-align: center;"><?= $no++ ?></td>
                        <td><?= date('d/m/y', strtotime($d['tanggal_pinjam'] ?? 'now')) ?></td>
                        <td><?= htmlspecialchars($d['peminjam'] ?? '-') ?></td>
                        <td style="font-size: 9px;"><?= htmlspecialchars($d['nip_peminjam'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['unit_kerja'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($d['nama_barang_108'] ?? '-') ?></td>
                        <td style="text-align: center; font-weight: bold;"><?= $d['jumlah'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_baik_pinjam'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_rusak_ringan_pinjam'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_rusak_berat_pinjam'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_perbaikan_pinjam'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_baik_kembali'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_rusak_ringan_kembali'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_rusak_berat_kembali'] ?? 0 ?></td>
                        <td style="text-align: center;"><?= $d['kondisi_perbaikan_kembali'] ?? 0 ?></td>
                        <td style="text-align: center;"><span class="status-badge <?= $status_class ?>"><?= ucfirst($d['status'] ?? '-') ?></span></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            
            <div class="signature">
                <div class="signature-box">
                    <p>Bojongsari, <?= date('d') . ' ' . nama_bulan_indo(date('n')) . ' ' . date('Y') ?></p>
                    <p><strong>Mengetahui,</strong><br>Kepala Sekolah</p>
                    <div class="signature-space"></div>
                    <div class="signature-name">
                        <strong>( _____________________ )</strong>
                        <br><span style="font-size: 10pt;">NIP. -</span>
                    </div>
                </div>
                <div class="signature-box">
                    <p>Bojongsari, <?= date('d') . ' ' . nama_bulan_indo(date('n')) . ' ' . date('Y') ?></p>
                    <p><strong>Pengurus Barang,</strong></p>
                    <div class="signature-space"></div>
                    <div class="signature-name">
                        <strong><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin') ?></strong>
                    </div>
                </div>
            </div>
            
            <div class="footer">
                <p>Dokumen ini dicetak pada <?= date('d/m/Y H:i') ?> WIB oleh <?= htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin') ?></p>
                <p>Sistem Informasi Inventaris Aset - SDN Curug 01</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Jika Dompdf tersedia, gunakan Dompdf
    require_once __DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php';

    // Setup Dompdf (use fully-qualified names to avoid `use` inside conditional)
    $options = new \Dompdf\Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'Arial');
    $options->set('defaultPaperSize', 'a4');
    $options->set('defaultPaperOrientation', 'landscape');
    
    $dompdf = new \Dompdf\Dompdf($options);
    
    // Generate HTML
    $html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 1.5cm; }
    body { font-family: Arial, sans-serif; font-size: 11px; color: #333; }
    .header { text-align: center; border-bottom: 3px double #1a365d; padding-bottom: 15px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 18px; color: #1a365d; }
    .header h2 { margin: 5px 0; font-size: 14px; color: #2c5282; }
    .header p { margin: 2px 0; font-size: 11px; color: #666; }
    .title { text-align: center; margin: 20px 0; padding: 10px; background: #1a365d; color: white; }
    .title h3 { margin: 0; font-size: 14px; }
    .title p { margin: 5px 0 0 0; font-size: 11px; }
    .summary-box { background: #f0f4f8; border: 1px solid #1a365d; padding: 15px; margin: 15px 0; }
    .summary-box h4 { margin: 0 0 10px 0; color: #1a365d; border-bottom: 1px solid #1a365d; padding-bottom: 5px; }
    .summary-grid { width: 100%; }
    .summary-grid td { width: 33%; padding: 8px; background: white; border: 1px solid #ddd; vertical-align: top; }
    .summary-item .label { font-size: 10px; color: #666; text-transform: uppercase; }
    .summary-item .value { font-size: 18px; font-weight: bold; color: #1a365d; }
    table.detail { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 9px; }
    table.detail th { background: #1a365d; color: white; padding: 6px 4px; border: 1px solid #0f2744; text-align: center; }
    table.detail td { padding: 5px 4px; border: 1px solid #ccc; vertical-align: top; }
    table.detail tr:nth-child(even) td { background: #f9f9f9; }
    .status-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 8px; font-weight: bold; }
    .status-dipinjam { background: #dbeafe; color: #1e40af; }
    .status-dikembalikan { background: #d1fae5; color: #065f46; }
    .status-terlambat { background: #fee2e2; color: #991b1b; }
    .signature { margin-top: 40px; }
    .signature-box { display: inline-block; width: 45%; text-align: center; vertical-align: top; }
    .signature-space { height: 70px; }
    .signature-name { border-top: 1px solid #333; padding-top: 5px; display: inline-block; min-width: 200px; }
    .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ccc; font-size: 9px; color: #666; text-align: center; }
</style>
</head>
<body>
    <div class="header">
        <h1>PEMERINTAH DAERAH</h1>
        <h2>DINAS PENDIDIKAN</h2>
        <p><strong>UPTD SDN CURUG 01</strong></p>
        <p>Bojongsari</p>
    </div>
    
    <div class="title">
        <h3>LAPORAN BULANAN PEMINJAMAN ASET</h3>
        <p>Periode: ' . $bulan_nama . '</p>
    </div>
    
    <div class="summary-box">
        <h4>RINGKASAN</h4>
        <table class="summary-grid" cellpadding="0" cellspacing="5">
            <tr>
                <td>
                    <div class="summary-item">
                        <div class="label">Total Peminjaman</div>
                        <div class="value">' . $arsip['total_peminjaman'] . '</div>
                        <div class="label">transaksi</div>
                    </div>
                </td>
                <td>
                    <div class="summary-item">
                        <div class="label">Total Unit Dipinjam</div>
                        <div class="value">' . $arsip['total_unit_dipinjam'] . '</div>
                        <div class="label">unit</div>
                    </div>
                </td>
                <td>
                    <div class="summary-item">
                        <div class="label">Sudah Dikembalikan</div>
                        <div class="value">' . $arsip['total_dikembalikan'] . '</div>
                        <div class="label">transaksi</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="summary-item">
                        <div class="label">Terlambat</div>
                        <div class="value">' . $arsip['total_terlambat'] . '</div>
                        <div class="label">transaksi</div>
                    </div>
                </td>
                <td>
                    <div class="summary-item">
                        <div class="label">Unit Rusak Kembali</div>
                        <div class="value">' . $arsip['total_rusak_kembali'] . '</div>
                        <div class="label">unit</div>
                    </div>
                </td>
                <td>
                    <div class="summary-item">
                        <div class="label">Dibuat Oleh</div>
                        <div class="value" style="font-size: 12px;">' . htmlspecialchars($arsip['dibuat_oleh_nama'] ?? 'Admin') . '</div>
                        <div class="label">' . date('d/m/Y', strtotime($arsip['created_at'])) . '</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    
    <h4 style="color: #1a365d; border-bottom: 2px solid #1a365d; padding-bottom: 5px;">DETAIL PEMINJAMAN</h4>
    
    <table class="detail">
        <thead>
            <tr>
                <th rowspan="2" width="3%">No</th>
                <th rowspan="2" width="8%">Tgl Pinjam</th>
                <th rowspan="2" width="12%">Peminjam</th>
                <th rowspan="2" width="8%">NIP</th>
                <th rowspan="2" width="8%">Unit Kerja</th>
                <th rowspan="2" width="15%">Nama Barang</th>
                <th rowspan="2" width="5%">Jml</th>
                <th colspan="4" style="background: #2c5282;">Saat Pinjam</th>
                <th colspan="4" style="background: #059669;">Saat Kembali</th>
                <th rowspan="2" width="8%">Status</th>
            </tr>
            <tr>
                <th style="background: #2c5282;">B</th>
                <th style="background: #2c5282;">RR</th>
                <th style="background: #2c5282;">RB</th>
                <th style="background: #2c5282;">P</th>
                <th style="background: #059669;">B</th>
                <th style="background: #059669;">RR</th>
                <th style="background: #059669;">RB</th>
                <th style="background: #059669;">P</th>
            </tr>
        </thead>
        <tbody>';
    
    if(is_array($detail)) {
        $no = 1;
        foreach($detail as $d) {
            $status_class = 'status-' . ($d['status'] ?? 'dipinjam');
            $html .= '<tr>
                <td style="text-align: center;">' . $no++ . '</td>
                <td>' . date('d/m/y', strtotime($d['tanggal_pinjam'] ?? 'now')) . '</td>
                <td>' . htmlspecialchars($d['peminjam'] ?? '-') . '</td>
                <td style="font-size: 8px;">' . htmlspecialchars($d['nip_peminjam'] ?? '-') . '</td>
                <td>' . htmlspecialchars($d['unit_kerja'] ?? '-') . '</td>
                <td>' . htmlspecialchars($d['nama_barang_108'] ?? '-') . '</td>
                <td style="text-align: center; font-weight: bold;">' . ($d['jumlah'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_baik_pinjam'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_rusak_ringan_pinjam'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_rusak_berat_pinjam'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_perbaikan_pinjam'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_baik_kembali'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_rusak_ringan_kembali'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_rusak_berat_kembali'] ?? 0) . '</td>
                <td style="text-align: center;">' . ($d['kondisi_perbaikan_kembali'] ?? 0) . '</td>
                <td style="text-align: center;"><span class="status-badge ' . $status_class . '">' . ucfirst($d['status'] ?? '-') . '</span></td>
            </tr>';
        }
    }
    
    $html .= '</tbody>
    </table>
    
    <div class="signature">
        <div class="signature-box">
            <p>Bojongsari, ' . date('d') . ' ' . nama_bulan_indo(date('n')) . ' ' . date('Y') . '</p>
            <p><strong>Mengetahui,</strong><br>Kepala Sekolah</p>
            <div class="signature-space"></div>
            <div class="signature-name">
                <strong>( _____________________ )</strong>
                <br><span style="font-size: 10pt;">NIP. -</span>
            </div>
        </div>
        <div class="signature-box">
            <p>Bojongsari, ' . date('d') . ' ' . nama_bulan_indo(date('n')) . ' ' . date('Y') . '</p>
            <p><strong>Pengurus Barang,</strong></p>
            <div class="signature-space"></div>
            <div class="signature-name">
                <strong>' . htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin') . '</strong>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>Dokumen ini dicetak pada ' . date('d/m/Y H:i') . ' WIB oleh ' . htmlspecialchars($_SESSION['nama_lengkap'] ?? 'Admin') . '</p>
        <p>Sistem Informasi Inventaris Aset - SDN Curug 01</p>
    </div>
</body>
</html>';
    
    // Load HTML ke Dompdf
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    
    // Output PDF
    $filename = "Laporan_Peminjaman_" . str_replace(' ', '_', $bulan_nama) . ".pdf";
    $dompdf->stream($filename, array("Attachment" => true));
    exit;
}

$flash_success = $_SESSION['flash_success'] ?? null;
$flash_error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$arsip_list = mysqli_query($conn, "SELECT a.*, u.nama_lengkap as dibuat_oleh_nama 
    FROM peminjaman_arsip_bulanan a 
    LEFT JOIN users u ON a.dibuat_oleh = u.id 
    ORDER BY a.tahun DESC, a.bulan DESC");

$tanggal_setting = intval(get_setting('tanggal_arsip_bulanan', 27));

// Cek apakah Dompdf tersedia
$dompdf_available = file_exists(__DIR__ . '/vendor/dompdf/dompdf/autoload.inc.php');
?>
<!DOCTYPE html>
<html lang="id" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true', sidebarOpen: false }" 
      :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Bulanan Peminjaman - Inventaris SDN Curug 01</title>
    
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
                        <a href="peminjaman.php" class="hover:text-primary">Peminjaman</a>
                        <i class="fas fa-chevron-right text-[8px]"></i>
                        <span class="text-primary font-semibold">Laporan Bulanan</span>
                    </nav>
                    <h2 class="text-xl lg:text-2xl font-bold text-primary dark:text-white flex items-center gap-2">
                        <i class="fas fa-file-alt"></i>
                        <span>Laporan Bulanan Peminjaman</span>
                    </h2>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="darkMode = !darkMode; localStorage.setItem('darkMode', darkMode)" 
                        class="p-2 lg:p-3 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                    <i class="fas" :class="darkMode ? 'fa-sun text-yellow-400' : 'fa-moon text-gray-600'"></i>
                </button>
                <?php if(isAdmin()): ?>
                <a href="pengaturan_arsip.php" class="hidden md:flex items-center gap-2 px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg shadow-md hover:shadow-lg transition-all text-sm">
                    <i class="fas fa-cog"></i>
                    <span>Setting Tanggal</span>
                </a>
                <?php endif; ?>
                <a href="peminjaman.php" class="hidden sm:flex items-center gap-2 px-4 py-2 lg:py-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 rounded-lg transition-all">
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
            
            <!-- Info & Form Buat Arsip -->
            <div class="bg-gradient-to-br from-purple-500 to-pink-600 rounded-2xl shadow-xl p-6 text-white relative overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-white/5 rounded-full -translate-y-32 translate-x-32"></div>
                <div class="relative">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="p-3 bg-white/20 backdrop-blur rounded-xl">
                            <i class="fas fa-archive text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl lg:text-2xl font-bold">Buat Arsip Bulanan</h3>
                            <p class="text-sm text-white/80">Backup data peminjaman per bulan untuk laporan operator</p>
                        </div>
                    </div>
                    
                    <div class="bg-white/10 backdrop-blur rounded-lg p-3 mb-4 text-sm">
                        <p class="text-white/90">
                            <i class="fas fa-info-circle mr-1"></i>
                            <strong>Info:</strong> Notifikasi arsip akan muncul otomatis setiap <strong class="text-yellow-300">tanggal <?= $tanggal_setting ?></strong> setiap bulan. 
                            <?php if(isAdmin()): ?>
                            <a href="pengaturan_arsip.php" class="underline hover:text-yellow-300">Ubah tanggal setting →</a>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <form method="POST" class="flex flex-col sm:flex-row gap-3 items-end">
                        <div class="flex-1">
                            <label class="text-xs font-semibold mb-1 block text-white/90">Pilih Bulan & Tahun</label>
                            <div class="grid grid-cols-2 gap-2">
                                <select name="bulan" required class="px-3 py-2 bg-white/20 backdrop-blur border border-white/30 rounded-lg text-white focus:outline-none focus:border-white text-sm">
                                    <?php 
                                    $bulan_list = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
                                    foreach($bulan_list as $idx => $bulan): ?>
                                    <option value="<?= $idx + 1 ?>" style="color: black;"><?= $bulan ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="tahun" required class="px-3 py-2 bg-white/20 backdrop-blur border border-white/30 rounded-lg text-white focus:outline-none focus:border-white text-sm">
                                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?= $y ?>" style="color: black;"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="buat_arsip_bulanan" 
                                class="px-6 py-2 bg-white text-purple-600 hover:bg-gray-100 rounded-lg shadow-md hover:shadow-lg transition-all font-semibold text-sm flex items-center gap-2">
                            <i class="fas fa-save"></i>
                            <span>Buat Arsip</span>
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- List Arsip -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md border border-gray-100 dark:border-gray-700 overflow-hidden">
                <div class="p-5 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-primary/5 to-transparent">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary/10 rounded-lg">
                            <i class="fas fa-archive text-primary text-lg"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 dark:text-white">Arsip Bulanan</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Daftar arsip peminjaman yang sudah dibuat</p>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="bg-gradient-to-r from-primary to-primary-dark text-white">
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Periode</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Transaksi</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Unit</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Selesai</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Terlambat</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Rusak</th>
                                <th class="px-4 py-4 text-left text-xs font-semibold uppercase tracking-wider">Dibuat</th>
                                <th class="px-4 py-4 text-center text-xs font-semibold uppercase tracking-wider">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php if(mysqli_num_rows($arsip_list) == 0): ?>
                            <tr>
                                <td colspan="8" class="px-4 py-12 text-center">
                                    <div class="flex flex-col items-center gap-3">
                                        <i class="fas fa-archive text-5xl text-gray-300 dark:text-gray-600"></i>
                                        <p class="text-gray-500 dark:text-gray-400 font-medium">Belum ada arsip</p>
                                        <p class="text-sm text-gray-400">Buat arsip pertama menggunakan form di atas</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else:
                                while($arsip = mysqli_fetch_assoc($arsip_list)): 
                                    $bulan_nama = nama_bulan_indo($arsip['bulan']) . ' ' . $arsip['tahun'];
                            ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-4 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-purple-500 to-pink-600 flex items-center justify-center text-white font-bold">
                                            <?= date('M', mktime(0,0,0,$arsip['bulan'],1,$arsip['tahun'])) ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-gray-800 dark:text-white"><?= $bulan_nama ?></div>
                                            <div class="text-xs text-gray-500"><?= date('d/m/Y H:i', strtotime($arsip['created_at'])) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-lg font-bold text-primary"><?= $arsip['total_peminjaman'] ?></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300"><?= $arsip['total_unit_dipinjam'] ?></span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-emerald-100 text-emerald-700 rounded-lg text-xs font-semibold">
                                        <i class="fas fa-check-circle text-[10px]"></i>
                                        <?= $arsip['total_dikembalikan'] ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if($arsip['total_terlambat'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 text-red-700 rounded-lg text-xs font-semibold">
                                        <i class="fas fa-exclamation-triangle text-[10px]"></i>
                                        <?= $arsip['total_terlambat'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4 text-center">
                                    <?php if($arsip['total_rusak_kembali'] > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-orange-100 text-orange-700 rounded-lg text-xs font-semibold">
                                        <i class="fas fa-times-circle text-[10px]"></i>
                                        <?= $arsip['total_rusak_kembali'] ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-gray-400">0</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm text-gray-700 dark:text-gray-300">
                                        <?= htmlspecialchars($arsip['dibuat_oleh_nama'] ?? 'System') ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex items-center justify-center gap-1">
                                        <!-- ✅ TOMBOL EXPORT PDF (BARU!) -->
                                        <a href="?export_pdf=<?= $arsip['id'] ?>" target="_blank"
                                           class="p-2 bg-red-100 hover:bg-red-500 text-red-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                           title="Export PDF">
                                            <i class="fas fa-file-pdf text-xs"></i>
                                        </a>
                                        <a href="?export_arsip=<?= $arsip['id'] ?>" 
                                           class="p-2 bg-emerald-100 hover:bg-emerald-500 text-emerald-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                           title="Export Excel">
                                            <i class="fas fa-file-excel text-xs"></i>
                                        </a>
                                        <?php if(canDelete()): ?>
                                        <button onclick="confirmHapusArsip(<?= $arsip['id'] ?>, '<?= $bulan_nama ?>')"
                                                class="p-2 bg-gray-100 hover:bg-red-500 text-gray-600 hover:text-white rounded-lg transition-all transform hover:scale-110"
                                                title="Hapus">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
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
function confirmHapusArsip(id, periode) {
    Swal.fire({
        title: 'Hapus Arsip?',
        html: `
            <p class="text-gray-600">Apakah Anda yakin ingin menghapus arsip:</p>
            <p class="font-bold text-primary mt-2">${periode}</p>
            <p class="text-xs text-red-500 mt-3">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                Data arsip akan hilang permanen!
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
            window.location.href = `?hapus_arsip=${id}`;
        }
    });
}
</script>

</body>
</html>