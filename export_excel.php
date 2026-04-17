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
        table { border-collapse: collapse; width: 100%; font-size: 11px; }
        th, td { border: 1px solid black; padding: 5px; text-align: left; }
        th { background-color: #667eea; color: white; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h3 style="text-align: center;">DATA INVENTARIS SEKOLAH - SDN CURUG 1</h3>
    <p>Tanggal Export: <?= date('d F Y') ?></p>
    
    <table>
        <thead>
            <tr>
                <th class="text-center">No</th>
                <th class="text-center">Kode Lokasi</th>
                <th class="text-center">Nama Unit</th>
                <th class="text-center">Sumber Pengadaan</th>
                <th class="text-center">Nama Barang</th>
                <th class="text-center">Spesifikasi</th>
                <th class="text-center">Satuan</th>
                <th class="text-center">Jumlah</th>
                <th class="text-center">Harga Satuan</th>
                <th class="text-center">Total</th>
                <th class="text-center">Sumber Dana</th>
                <th class="text-center">Pengurus Barang</th>
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
                <td><?= $row['nama_barang_108'] ?></td>
                <td><?= $row['spesifikasi_nama_barang'] ?></td>
                <td class="text-center"><?= $row['satuan'] ?></td>
                <td class="text-center"><?= $row['jumlah'] ?></td>
                <td class="text-right">Rp <?= number_format($row['harga_satuan'], 0, ',', '.') ?></td>
                <td class="text-right">Rp <?= number_format($row['total'], 0, ',', '.') ?></td>
                <td><?= $row['sumber_dana'] ?></td>
                <td><?= $row['nama_pengurus_barang'] ?></td>
            </tr>
            <?php endwhile; ?>
            <tr style="font-weight: bold; background: #f0f0f0;">
                <td colspan="9" class="text-right">GRAND TOTAL:</td>
                <td class="text-right">Rp <?= number_format($grand_total, 0, ',', '.') ?></td>
                <td colspan="2"></td>
            </tr>
        </tbody>
    </table>
</body>
</html>