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

// Variabel untuk mendeteksi halaman aktif agar sidebar berwarna putih
$current_page = 'kondisi_aset.php'; 
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Kondisi - <?= $aset['nama_barang_108'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a365d;
            --primary-dark: #0f2744;
            --white: #ffffff;
            --gray-light: #f7fafc;
            --gray-medium: #e2e8f0;
            --gray-dark: #4a5568;
        }

        body { 
            background-color: var(--gray-light);
            color: var(--gray-dark);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .sidebar {
            background-color: var(--primary);
            min-height: 100vh;
            color: var(--white);
            padding: 0;
            position: fixed;
            width: 16.666667%; /* col-md-2 */
        }

        .sidebar-brand {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-logo {
            width: 120px;
            height: 120px; /* Diperbaiki dari 120x */
            display: block;
            margin: 0 auto 15px auto;
            object-fit: contain;
            border-radius: 50%;
            padding: 5px;
            transition: 0.3s;
        }

        .sidebar-logo:hover {
            transform: scale(1.08);
        }

        /* Mengikuti ukuran font 35px sesuai request Anda */
        .sidebar-brand h5 {
            margin-top: 10px;
            font-size: 35px; 
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }

        .sidebar-brand small {
            color: #cbd5e1;
            font-size: 12px;
            opacity: 0.7;
            display: block;
        }

        .sidebar-menu { padding: 20px 10px; }

        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            padding: 14px 20px;
            display: block;
            margin-bottom: 5px;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
        }

        .sidebar-menu a:hover {
            background-color: rgba(255, 255, 255, 0.1);
            color: var(--white);
            padding-left: 25px;
        }

        .sidebar-menu a.active {
            background-color: var(--white);
            color: var(--primary);
            font-weight: 600;
        }

        .sidebar-menu a i { margin-right: 12px; width: 20px; text-align: center; }
        /* --- MAIN CONTENT --- */
        .main-content {
            background-color: var(--gray-light);
            padding: 30px;
            margin-left: 16.666667%; /* Memberi ruang untuk sidebar fixed */
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--gray-medium);
        }

        .page-header h2 { color: var(--primary); font-weight: 600; font-size: 24px; }

        .card {
            background-color: var(--white);
            border: 1px solid var(--gray-medium);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .card-header {
            background-color: var(--white);
            border-bottom: 2px solid var(--primary);
            padding: 15px 20px;
            font-weight: 600;
            color: var(--primary);
        }

        .table thead th {
            background-color: var(--primary);
            color: var(--white);
            padding: 15px;
            font-size: 13px;
            text-transform: uppercase;
        }

        .badge-success { background-color: #38a169; }
        .badge-danger { background-color: #e53e3e; }
        .badge-warning { background-color: #d69e2e; }
        .badge-info { background-color: #3182ce; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <div class="sidebar-brand">
                <img src="assets/img/logo.png" class="sidebar-logo">
                <h4>Inventaris Sekolah</h4>
                <h6>SDN Curug 01</h6>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="<?= ($current_page=='dashboard.php') ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="ruangan.php" class="<?= ($current_page=='ruangan.php') ? 'active' : '' ?>">
                    <i class="fas fa-door-open"></i> Manajemen Ruangan
                </a>
                <a href="tambah.php" class="<?= ($current_page=='tambah.php') ? 'active' : '' ?>">
                    <i class="fas fa-plus-circle"></i> Tambah Aset
                </a>
                <a href="kondisi_aset.php" class="<?= ($current_page=='kondisi_aset.php') ? 'active' : '' ?>">
                    <i class="fas fa-tools"></i> Kondisi Aset
                </a>
                <a href="export_excel.php" class="<?= ($current_page=='export_excel.php') ? 'active' : '' ?>">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-history"></i> Riwayat Kondisi Aset</h2>
                <a href="kondisi_aset.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
            </div>

            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i> Informasi Aset
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Nama Barang:</strong><br><?= $aset['nama_barang_108'] ?></p>
                            <p class="mb-0"><strong>Spesifikasi:</strong><br><?= $aset['spesifikasi_nama_barang'] ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-2"><strong>Kondisi Saat Ini:</strong><br> 
                                <span class="badge badge-<?= $aset['kondisi_aset']=='Baik'?'success':'danger' ?>">
                                    <?= $aset['kondisi_aset'] ?>
                                </span>
                            </p>
                            <p class="mb-0"><strong>Total Perolehan:</strong><br><?= formatRupiah($aset['total']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

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