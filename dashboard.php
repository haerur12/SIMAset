<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Statistik
$total_aset = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris")->fetch_assoc()['total'];
$total_pemerintah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Pemerintah'")->fetch_assoc()['total'];
$total_sekolah = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE sumber_pengadaan = 'Sekolah'")->fetch_assoc()['total'];
$total_nilai = mysqli_query($conn, "SELECT SUM(total) as total FROM inventaris")->fetch_assoc()['total'];

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$where = "";
if($search) {
    $where = "WHERE spesifikasi_nama_barang LIKE '%$search%' OR nama_barang_108 LIKE '%$search%'";
}

$result = mysqli_query($conn, "SELECT * FROM inventaris $where ORDER BY created_at DESC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Inventaris Sekolah</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
    /* ========================================
       COLOR PALETTE - Professional & Classy
       ======================================== */
    :root {
        --primary: #1a365d;       /* Navy Blue - Utama */
        --primary-dark: #0f2744;  /* Navy Dark - Hover */
        --white: #ffffff;         /* White - Background */
        --gray-light: #f7fafc;    /* Light Gray - Body */
        --gray-medium: #e2e8f0;   /* Medium Gray - Border */
        --gray-dark: #4a5568;     /* Dark Gray - Text */
    }

    body { 
        background-color: var(--gray-light);
        color: var(--gray-dark);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    /* Sidebar */
    .sidebar {
        background-color: var(--primary);
        min-height: 100vh;
        color: var(--white);
        padding: 0;
    }

    .sidebar-brand {
        padding: 30px 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .sidebar-brand i { font-size: 40px; margin-bottom: 10px; }
    .sidebar-brand h5 { font-weight: 600; font-size: 16px; margin-bottom: 5px; }
    .sidebar-brand small { font-size: 12px; opacity: 0.7; }

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

    /* Main Content */
    .main-content {
        background-color: var(--gray-light);
        padding: 30px;
    }

    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 2px solid var(--gray-medium);
    }

    .page-header h2 {
        color: var(--primary);
        font-weight: 600;
        font-size: 24px;
    }

    .page-header h2 i { margin-right: 10px; }

    /* Cards */
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

    .card-body { padding: 20px; }

    /* Stat Cards */
    .stat-card {
        background-color: var(--white);
        border-radius: 8px;
        padding: 25px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        border-left: 4px solid var(--primary);
        transition: all 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }

    .stat-card h6 {
        color: var(--gray-dark);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .stat-card h3 {
        color: var(--primary);
        font-weight: 700;
        font-size: 32px;
        margin-bottom: 0;
    }

    /* Tables */
    .table {
        background-color: var(--white);
        margin-bottom: 0;
    }

    .table thead th {
        background-color: var(--primary);
        color: var(--white);
        border: none;
        padding: 15px;
        font-weight: 500;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 15px;
        vertical-align: middle;
        border-color: var(--gray-medium);
        font-size: 14px;
    }

    .table tbody tr:hover {
        background-color: var(--gray-light);
    }

    /* Badges */
    .badge {
        padding: 6px 12px;
        font-weight: 500;
        font-size: 12px;
        border-radius: 4px;
    }

    .badge-pemerintah {
        background-color: var(--primary);
        color: var(--white);
    }

    .badge-sekolah {
        background-color: #d69e2e; /* Yellow/Warning */
        color: var(--white);
    }

    .badge-bos {
        background-color: #38a169; /* Green/Success */
        color: var(--white);
    }

    /* Buttons */
    .btn {
        border-radius: 6px;
        padding: 10px 20px;
        font-weight: 500;
        font-size: 14px;
        transition: all 0.3s;
    }

    .btn-sm { padding: 6px 12px; font-size: 13px; }

    .btn-primary {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .btn-primary:hover {
        background-color: var(--primary-dark);
        border-color: var(--primary-dark);
    }

    .btn-warning {
        background-color: #d69e2e;
        border-color: #d69e2e;
        color: var(--white);
    }

    .btn-danger {
        background-color: #e53e3e;
        border-color: #e53e3e;
    }

    .btn-secondary {
        background-color: var(--gray-dark);
        border-color: var(--gray-dark);
        color: var(--white);
    }

    /* Forms */
    .form-control:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
    }

    /* Utilities */
    .text-primary { color: var(--primary) !important; }
    .shadow-sm { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important; }
</style>
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
           <a href="kondisi_aset.php" class="active"><i class="fas fa-tools"></i> Kondisi Aset</a>  <!-- TAMBAH INI -->
           <a href="export_excel.php"><i class="fas fa-file-excel"></i> Export Excel</a>
           <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard Inventaris</h2>
                <span><?= date('d F Y') ?></span>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6>Total Aset</h6>
                        <h3><?= $total_aset ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6>Aset Pemerintah</h6>
                        <h3><?= $total_pemerintah ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6>Aset Sekolah</h6>
                        <h3><?= $total_sekolah ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <h6>Total Nilai</h6>
                        <h3><?= formatRupiah($total_nilai) ?></h3>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Data Inventaris
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="GET" class="d-flex mb-3">
                        <input type="text" name="search" class="form-control me-2" placeholder="Cari barang..." value="<?= $search ?>">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    </form>

                    <!-- Table Content -->
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Sumber</th>
                                    <th>Nama Barang</th>
                                    <th>Spesifikasi</th>
                                    <th>Jumlah</th>
                                    <th>Harga Satuan</th>
                                    <th>Total</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $badgeClass = $row['sumber_pengadaan'] == 'Pemerintah' ? 'badge-pemerintah' : 
                                                 ($row['sumber_pengadaan'] == 'Sekolah' ? 'badge-sekolah' : 'badge-bos');
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= $row['sumber_pengadaan'] ?></span></td>
                                    <td><?= $row['nama_barang_108'] ?></td>
                                    <td><?= substr($row['spesifikasi_nama_barang'], 0, 50) ?>...</td>
                                    <td><?= $row['jumlah'] ?> <?= $row['satuan'] ?></td>
                                    <td><?= formatRupiah($row['harga_satuan']) ?></td>
                                    <td><?= formatRupiah($row['total']) ?></td>
                                    <td>
                                        <a href="edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
                                        <a href="hapus.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin?')"><i class="fas fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <nav>
                        <ul class="pagination justify-content-end">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= $search ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>