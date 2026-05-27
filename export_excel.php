<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Inventaris_SDN_Curug1_" . date('Y-m-d') . ".xls");

$result = mysqli_query($conn, "SELECT * FROM inventaris ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0.5cm; size: landscape; }
        table { border-collapse: collapse; width: 100%; font-size: 9px; }
        th, td { border: 1px solid black; padding: 4px; text-align: left; vertical-align: top; }
        th { background-color: #1a365d; color: white; font-weight: bold; white-space: nowrap; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .wrap { white-space: normal; word-wrap: break-word; max-width: 150px; }
    </style>
</head>
<body>
    <h3 style="text-align: center; margin: 10px 0;">DATA INVENTARIS SEKOLAH - SDN CURUG 01</h3>
    <p style="text-align: center; margin: 0 0 15px 0;">Tanggal Export: <?= date('d F Y') ?></p>
    
    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th>Kode Lokasi</th>
                <th>Nama Unit</th>
                <th>Sumber Pengadaan</th>
                <th>Bentuk Kontrak</th>
                <th>No Kontrak</th>
                <th>Tgl Kontrak</th>
                <th>Pihak ke-3</th>
                <th>No BAST</th>
                <th>Tgl BAST</th>
                <th>Nama PPK</th>
                <th>Pengurus Barang</th>
                <th>No Surat Pernyataan</th>
                <th>Tgl Pernyataan</th>
                <th>Kode Sub Keg</th>
                <th>Nama Sub Keg</th>
                <th>Kode Rekening</th>
                <th>Nama Rekening</th>
                <th>Kode Barang</th>
                <th>Nama Barang</th>
                <th>Spesifikasi</th>
                <th>Satuan</th>
                <th>Jumlah</th>
                <th>Harga Satuan</th>
                <th>Total</th>
                <th>Judul</th>
                <th>Pencipta</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $grand_total = 0;
            while($row = mysqli_fetch_assoc($result)): 
                $grand_total += $row['total'];
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= $row['kode_lokasi'] ?></td>
                <td><?= $row['nama_unit_lokasi'] ?></td>
                <td class="text-center"><?= $row['sumber_pengadaan'] ?></td>
                <td class="text-center"><?= $row['bentuk_kontrak'] ?></td>
                <td><?= $row['no_dokumen_kontrak'] ?></td>
                <td class="text-center"><?= $row['tanggal_kontrak'] ? date('d/m/Y', strtotime($row['tanggal_kontrak'])) : '-' ?></td>
                <td><?= $row['pihak_ke_3'] ?></td>
                <td><?= $row['no_bast'] ?></td>
                <td class="text-center"><?= $row['tanggal_bast'] ? date('d/m/Y', strtotime($row['tanggal_bast'])) : '-' ?></td>
                <td><?= $row['nama_ppk'] ?></td>
                <td><?= $row['nama_pengurus_barang'] ?></td>
                <td><?= $row['no_surat_pernyataan'] ?></td>
                <td class="text-center"><?= $row['tanggal_pernyataan'] ? date('d/m/Y', strtotime($row['tanggal_pernyataan'])) : '-' ?></td>
                <td class="text-center"><?= $row['kode_sub_kegiatan'] ?></td>
                <td class="wrap"><?= $row['nama_sub_kegiatan'] ?></td>
                <td class="text-center"><?= $row['kode_rekening_belanja'] ?></td>
                <td class="wrap"><?= $row['nama_rekening_belanja'] ?></td>
                <td class="text-center"><?= $row['kode_barang_108'] ?></td>
                <td class="wrap"><strong><?= $row['nama_barang_108'] ?></strong></td>
                <td class="wrap"><?= $row['spesifikasi_nama_barang'] ?></td>
                <td class="text-center"><?= $row['satuan'] ?></td>
                <td class="text-center"><?= $row['jumlah'] ?></td>
                <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right"><strong>Rp <?= number_format($row['total'], 0, ',', '.') ?></strong></td>
                <td class="wrap"><?= $row['judul'] ?></td>
                <td class="wrap"><?= $row['pencipta'] ?></td>
                <td class="wrap"><?= $row['keterangan'] ?></td>
            </tr>
            <?php endwhile; ?>
            <tr style="font-weight: bold; background: #e2e8f0;">
                <td colspan="24" class="text-right">GRAND TOTAL:</td>
                <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                <td colspan="3"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>