<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Hapus Ruangan
if(isset($_GET['hapus'])) {
    $id = $_GET['hapus'];
    mysqli_query($conn, "DELETE FROM ruangan WHERE id = $id");
    echo "<script>alert('Ruangan berhasil dihapus!'); window.location='ruangan.php';</script>";
}

// Pagination
$limit = 10;
$page = isset($_GET['page']) ? $_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Search
$search = isset($_GET['cari']) ? $_GET['cari'] : '';
$where = "";
if($search) {
    $where = "WHERE nama_ruangan LIKE '%$search%' OR kode_ruangan LIKE '%$search%'";
}

$result = mysqli_query($conn, "SELECT * FROM ruangan $where ORDER BY kode_ruangan ASC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM ruangan $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// Statistik
$total_ruangan = mysqli_query($conn, "SELECT COUNT(*) as total FROM ruangan")->fetch_assoc()['total'];
$total_gedung = mysqli_query($conn, "SELECT COUNT(DISTINCT gedung) as total FROM ruangan")->fetch_assoc()['total'];
$total_kapasitas = mysqli_query($conn, "SELECT SUM(kapasitas) as total FROM ruangan")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Ruangan - Inventaris SDN Curug 01</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========================================
           COLOR PALETTE - Professional & Classy
           ======================================== */
        :root {
            --primary: #1a365d;       /* Navy Blue - Utama */
            --primary-dark: #0f2744;  /* Navy Dark - Hover */
            --primary-light: #2d4a7c; /* Navy Light - Accent */
            --white: #ffffff;         /* White - Background */
            --gray-light: #f7fafc;    /* Light Gray - Body */
            --gray-medium: #e2e8f0;   /* Medium Gray - Border */
            --gray-dark: #4a5568;     /* Dark Gray - Text */
            --success: #38a169;       /* Green - Success */
            --warning: #d69e2e;       /* Yellow - Warning */
            --danger: #e53e3e;        /* Red - Danger */
            --info: #4299e1;          /* Blue - Info */
        }

        /* ========================================
           GLOBAL STYLES
           ======================================== */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--gray-light);
            color: var(--gray-dark);
            line-height: 1.6;
        }

        /* ========================================
           SIDEBAR
           ======================================== */
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

        .sidebar-brand i {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .sidebar-brand h5 {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }

        .sidebar-brand small {
            font-size: 12px;
            opacity: 0.7;
        }

        .sidebar-menu {
            padding: 20px 10px;
        }

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

        .sidebar-menu a i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
        }

        /* ========================================
           MAIN CONTENT
           ======================================== */
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

        .page-header h2 i {
            margin-right: 10px;
        }

        /* ========================================
           STATISTICS CARDS
           ======================================== */
        .stat-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary);
            transition: all 0.3s;
            color: var(--white);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
        }

        .stat-card.blue {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }

        .stat-card.green {
            background: linear-gradient(135deg, var(--success) 0%, #080857 100%);
        }

        .stat-card.orange {
            background: linear-gradient(135deg, #105ac0 0%, #35469d 100%);
        }

        .stat-card h6 {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .stat-card h3 {
            font-weight: 700;
            font-size: 32px;
            margin-bottom: 0;
        }

        .stat-card i {
            font-size: 40px;
            opacity: 0.3;
        }

        /* ========================================
           CARDS & TABLES
           ======================================== */
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

        .card-body {
            padding: 20px;
        }

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

        /* ========================================
           BADGES & BUTTONS
           ======================================== */
        .badge {
            padding: 6px 12px;
            font-weight: 500;
            font-size: 12px;
            border-radius: 4px;
        }

        .badge-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn {
            border-radius: 6px;
            padding: 10px 20px;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-warning {
            background-color: var(--warning);
            border-color: var(--warning);
            color: var(--white);
        }

        .btn-danger {
            background-color: var(--danger);
            border-color: var(--danger);
        }

        .btn-info {
            background-color: var(--info);
            border-color: var(--info);
        }

        /* ========================================
           FORMS & SEARCH
           ======================================== */
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

        /* ========================================
           UTILITIES
           ======================================== */
        .text-primary {
            color: var(--primary) !important;
        }

        .shadow-sm {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
        }

        /* ========================================
           RESPONSIVE
           ======================================== */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                transition: all 0.3s;
                z-index: 1000;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .stat-card h3 {
                font-size: 24px;
            }
        }
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
                <a href="ruangan.php" class="active"><i class="fas fa-door-open"></i> Manajemen Ruangan</a>
                <a href="tambah.php"><i class="fas fa-plus-circle"></i> Tambah Aset</a>
                <a href="export_excel.php"><i class="fas fa-file-excel"></i> Export Excel</a>
                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-door-open"></i> Manajemen Ruangan</h2>
                <a href="tambah_ruangan.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Tambah Ruangan
                </a>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card blue">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Total Ruangan</h6>
                                <h3><?= $total_ruangan ?></h3>
                            </div>
                            <i class="fas fa-door-open"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card green">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Total Gedung</h6>
                                <h3><?= $total_gedung ?></h3>
                            </div>
                            <i class="fas fa-building"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card orange">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Total Kapasitas</h6>
                                <h3><?= $total_kapasitas ?> Orang</h3>
                            </div>
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Ruangan</h5>
                    <div class="search-box">
                        <form method="GET" class="d-flex">
                            <input type="text" name="cari" class="form-control me-2" placeholder="Cari ruangan..." value="<?= $search ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Kode</th>
                                    <th>Nama Ruangan</th>
                                    <th>Gedung</th>
                                    <th>Lantai</th>
                                    <th>Kapasitas</th>
                                    <th>Fungsi</th>
                                    <th>Penanggung Jawab</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><span class="badge badge-primary"><?= $row['kode_ruangan'] ?></span></td>
                                    <td><strong><?= $row['nama_ruangan'] ?></strong></td>
                                    <td><?= $row['gedung'] ?></td>
                                    <td>Lantai <?= $row['lantai'] ?></td>
                                    <td><?= $row['kapasitas'] ?> Orang</td>
                                    <td><?= $row['fungsi_ruangan'] ?></td>
                                    <td><?= $row['penanggung_jawab'] ?></td>
                                    <td>
                                        <a href="edit_ruangan.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus ruangan ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
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
                                <a class="page-link" href="?page=<?= $i ?>&cari=<?= $search ?>"><?= $i ?></a>
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