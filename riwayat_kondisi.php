<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$aset = mysqli_query($conn, "SELECT * FROM inventaris WHERE id = $id")->fetch_assoc();

$riwayat = mysqli_query($conn, "SELECT k.*, i.nama_barang_108 
    FROM kondisi_aset k 
    JOIN inventaris i ON k.inventaris_id = i.id 
    WHERE k.inventaris_id = $id 
    ORDER BY k.created_at DESC");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Riwayat Kondisi - <?= $aset['nama_barang_108'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-school"></i>
                <h5>Inventaris Sekolah</h5>
                <small>SDN Curug 01</small>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="ruangan.php"><i class="fas fa-door-open"></i> Manajemen Ruangan</a>
                <a href="tambah.php"><i class="fas fa-plus-circle"></i> Tambah Aset</a>
                <a href="kondisi_aset.php" class="active"><i class="fas fa-tools"></i> Kondisi Aset</a>
                <a href="export_excel.php"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-history"></i> Riwayat Kondisi Aset</h2>
                <a href="kondisi_aset.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>

            <!-- Info Aset -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-box"></i> Informasi Aset
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Nama Barang:</strong> <?= $aset['nama_barang_108'] ?></p>
                            <p><strong>Spesifikasi:</strong> <?= $aset['spesifikasi_nama_barang'] ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Kondisi Saat Ini:</strong> <span class="badge badge-<?= $aset['kondisi_aset']=='Baik'?'success':'danger' ?>"><?= $aset['kondisi_aset'] ?></span></p>
                            <p><strong>Total Perolehan:</strong> <?= formatRupiah($aset['total']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Riwayat -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-timeline"></i> Timeline Perubahan Kondisi
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tanggal Cek</th>
                                    <th>Kondisi</th>
                                    <th>Keterangan</th>
                                    <th>Petugas</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($row = mysqli_fetch_assoc($riwayat)): 
                                    $badgeClass = $row['kondisi'] == 'Baik' ? 'badge-success' : 
                                                 ($row['kondisi'] == 'Rusak Ringan' ? 'badge-warning' : 
                                                 ($row['kondisi'] == 'Dalam Perbaikan' ? 'badge-info' : 'badge-danger'));
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= formatTanggal($row['tanggal_cek']) ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= $row['kondisi'] ?></span></td>
                                    <td><?= $row['keterangan'] ?></td>
                                    <td><?= $row['petugas'] ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>