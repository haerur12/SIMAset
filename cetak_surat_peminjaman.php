<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$id = intval($_GET['id'] ?? 0);
$data = mysqli_query($conn, "SELECT p.*, i.nama_barang_108, i.spesifikasi_nama_barang, i.satuan,
    r.nama_ruangan
    FROM peminjaman_aset p
    LEFT JOIN inventaris i ON p.inventaris_id = i.id
    LEFT JOIN ruangan r ON i.ruangan_id = r.id
    WHERE p.id = $id")->fetch_assoc();

if(!$data) {
    die("Data tidak ditemukan");
}

function formatTglID($tgl) {
    if(!$tgl) return '-';
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('d', strtotime($tgl)) . ' ' . $bulan[date('n', strtotime($tgl))-1] . ' ' . date('Y', strtotime($tgl));
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Surat Peminjaman - <?= $data['id'] ?></title>
    <style>
        @page { margin: 2cm; size: A4; }
        body { font-family: 'Times New Roman', serif; font-size: 12pt; line-height: 1.6; color: #000; }
        .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 16pt; font-weight: bold; }
        .header h2 { margin: 5px 0 0 0; font-size: 14pt; }
        .header p { margin: 2px 0; font-size: 11pt; }
        .title { text-align: center; margin: 20px 0; }
        .title h3 { margin: 0; font-size: 14pt; text-decoration: underline; }
        .title p { margin: 5px 0 0 0; font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        table td { padding: 6px 8px; vertical-align: top; }
        table.bordered td, table.bordered th { border: 1px solid #000; }
        table.bordered th { background: #f0f0f0; text-align: center; font-weight: bold; }
        .signature { margin-top: 40px; }
        .signature-box { display: inline-block; width: 45%; text-align: center; vertical-align: top; }
        .signature-space { height: 80px; }
        .signature-name { border-top: 1px solid #000; padding-top: 5px; display: inline-block; min-width: 200px; }
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
        }
        .print-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #1a365d;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .print-btn:hover { background: #0f2744; }
    </style>
</head>
<body>
    <button class="print-btn no-print" onclick="window.print()">🖨️ Cetak / Simpan PDF</button>
    
    <div class="header">
        <h1>PEMERINTAH DAERAH</h1>
        <h2>DINAS PENDIDIKAN</h2>
        <p>UPTD SDN CURUG 01</p>
        <p style="font-size: 10pt;">Bojongsari</p>
    </div>
    
    <div class="title">
        <h3>SURAT PEMINJAMAN ASET</h3>
        <p>Nomor: <?= str_pad($data['id'], 4, '0', STR_PAD_LEFT) ?>/SPM/SDN.CR01/<?= date('Y') ?></p>
    </div>
    
    <p style="margin-bottom: 15px;">Yang bertanda tangan di bawah ini:</p>
    
    <table>
        <tr>
            <td width="30%">Nama Peminjam</td>
            <td width="5%">:</td>
            <td><strong><?= htmlspecialchars($data['peminjam']) ?></strong></td>
        </tr>
        <?php if($data['nip_peminjam']): ?>
        <tr>
            <td>NIP</td>
            <td>:</td>
            <td><?= htmlspecialchars($data['nip_peminjam']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if($data['unit_kerja']): ?>
        <tr>
            <td>Unit Kerja</td>
            <td>:</td>
            <td><?= htmlspecialchars($data['unit_kerja']) ?></td>
        </tr>
        <?php endif; ?>
        <?php if($data['no_hp']): ?>
        <tr>
            <td>No. HP</td>
            <td>:</td>
            <td><?= htmlspecialchars($data['no_hp']) ?></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <p style="margin: 20px 0 10px 0;">Dengan ini meminjam aset sekolah berupa:</p>
    
    <table class="bordered">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th width="40%">Nama Barang</th>
                <th width="30%">Spesifikasi</th>
                <th width="10%">Jumlah</th>
                <th width="15%">Kondisi</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;">1</td>
                <td><?= htmlspecialchars($data['nama_barang_108']) ?></td>
                <td style="font-size: 10pt;"><?= htmlspecialchars($data['spesifikasi_nama_barang']) ?></td>
                <td style="text-align: center;"><?= $data['jumlah'] ?> <?= $data['satuan'] ?></td>
                <td style="text-align: center;"><?= $data['kondisi_sebelum'] ?: 'Baik' ?></td>
            </tr>
        </tbody>
    </table>
    
    <p style="margin: 15px 0;">Untuk keperluan: <strong><?= htmlspecialchars($data['keperluan'] ?: '-') ?></strong></p>
    
    <table style="margin: 15px 0;">
        <tr>
            <td width="50%">Tanggal Peminjaman</td>
            <td>: <strong><?= formatTglID($data['tanggal_pinjam']) ?></strong></td>
        </tr>
        <tr>
            <td>Batas Pengembalian</td>
            <td>: <strong><?= formatTglID($data['tanggal_kembali_rencana']) ?></strong></td>
        </tr>
        <?php if($data['tanggal_kembali_aktual']): ?>
        <tr>
            <td>Tanggal Dikembalikan</td>
            <td>: <strong><?= formatTglID($data['tanggal_kembali_aktual']) ?></strong></td>
        </tr>
        <?php endif; ?>
    </table>
    
    <p style="margin-top: 20px;">Demikian surat peminjaman ini dibuat untuk dipergunakan sebagaimana mestinya. Aset yang dipinjam agar dijaga dan dikembalikan dalam kondisi baik sesuai kondisi saat dipinjam.</p>
    
    <div class="signature">
        <div class="signature-box">
            <p>Bojongsari, <?= formatTglID(date('Y-m-d')) ?></p>
            <p><strong>Peminjam,</strong></p>
            <div class="signature-space"></div>
            <div class="signature-name">
                <strong><?= htmlspecialchars($data['peminjam']) ?></strong>
                <?php if($data['nip_peminjam']): ?>
                <br><span style="font-size: 10pt;">NIP. <?= htmlspecialchars($data['nip_peminjam']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="signature-box">
            <p>Bojongsari, <?= formatTglID(date('Y-m-d')) ?></p>
            <p><strong>Mengetahui,</strong><br>Kepala Sekolah</p>
            <div class="signature-space"></div>
            <div class="signature-name">
                <strong>( _____________________ )</strong>
                <br><span style="font-size: 10pt;">NIP. -</span>
            </div>
        </div>
    </div>
</body>
</html>