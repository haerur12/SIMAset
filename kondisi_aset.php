<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Update kondisi
if(isset($_POST['update_kondisi'])) {
    $inventaris_id = intval($_POST['inventaris_id']);
    $kondisi = mysqli_real_escape_string($conn, $_POST['kondisi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $petugas = mysqli_real_escape_string($conn, $_SESSION['nama_lengkap']);
    $tanggal_cek = date('Y-m-d');

    // Insert ke history kondisi aset
    mysqli_query($conn, "INSERT INTO kondisi_aset (inventaris_id, kondisi, tanggal_cek, keterangan, petugas) VALUES ($inventaris_id, '$kondisi', '$tanggal_cek', '$keterangan', '$petugas')");

    echo "<script>alert('Kondisi aset berhasil diupdate!'); window.location='kondisi_aset.php';</script>";
}

// Filter kondisi
// Filter kondisi
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where = "";
if($filter != 'all') {
    $where = "WHERE ks.kondisi = '$filter'";
}

// Search
$search = isset($_GET['search']) ? $_GET['search'] : '';
if($search) {
    if($where) {
        $where .= " AND (i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%')";
    } else {
        $where = "WHERE i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%'";
    }
}

$query = "SELECT i.*, r.nama_ruangan, ks.kondisi AS kondisi_aset, ks.tanggal_cek AS last_tanggal_cek, ks.keterangan AS last_keterangan
          FROM inventaris i
          LEFT JOIN ruangan r ON i.ruangan_id = r.id
          LEFT JOIN (
              SELECT k1.inventaris_id, k1.kondisi, k1.tanggal_cek, k1.keterangan
              FROM kondisi_aset k1
              INNER JOIN (
                  SELECT inventaris_id, MAX(created_at) AS max_created_at
                  FROM kondisi_aset
                  GROUP BY inventaris_id
              ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
          ) ks ON ks.inventaris_id = i.id
          $where
          ORDER BY i.created_at DESC";
$result = mysqli_query($conn, $query);

// Statistik kondisi
$stat_baik = mysqli_query($conn, "SELECT COUNT(*) as total FROM (
    SELECT k1.inventaris_id
    FROM kondisi_aset k1
    INNER JOIN (
        SELECT inventaris_id, MAX(created_at) AS max_created_at
        FROM kondisi_aset
        GROUP BY inventaris_id
    ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    WHERE k1.kondisi = 'Baik'
) AS latest")->fetch_assoc()['total'];
$stat_rusak_ringan = mysqli_query($conn, "SELECT COUNT(*) as total FROM (
    SELECT k1.inventaris_id
    FROM kondisi_aset k1
    INNER JOIN (
        SELECT inventaris_id, MAX(created_at) AS max_created_at
        FROM kondisi_aset
        GROUP BY inventaris_id
    ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    WHERE k1.kondisi = 'Rusak Ringan'
) AS latest")->fetch_assoc()['total'];
$stat_rusak_berat = mysqli_query($conn, "SELECT COUNT(*) as total FROM (
    SELECT k1.inventaris_id
    FROM kondisi_aset k1
    INNER JOIN (
        SELECT inventaris_id, MAX(created_at) AS max_created_at
        FROM kondisi_aset
        GROUP BY inventaris_id
    ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    WHERE k1.kondisi = 'Rusak Berat'
) AS latest")->fetch_assoc()['total'];
$stat_perbaikan = mysqli_query($conn, "SELECT COUNT(*) as total FROM (
    SELECT k1.inventaris_id
    FROM kondisi_aset k1
    INNER JOIN (
        SELECT inventaris_id, MAX(created_at) AS max_created_at
        FROM kondisi_aset
        GROUP BY inventaris_id
    ) k2 ON k1.inventaris_id = k2.inventaris_id AND k1.created_at = k2.max_created_at
    WHERE k1.kondisi = 'Dalam Perbaikan'
) AS latest")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kondisi Aset - Inventaris SDN Curug 01</title>
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

    .sidebar-brand{
    padding:30px 20px;
    text-align:center;
    border-bottom:1px solid rgba(255,255,255,0.1);
}

    .sidebar-logo{
        width:120px;
        height:120x;

        display:block;
        margin:0 auto 15px auto;

        object-fit:contain;

        border-radius:50%;
        

        padding:5px;

        transition:0.3s;
    }

    .sidebar-logo:hover{
        transform:scale(1.08);
    }

    .sidebar-brand h5{
        margin-top:10px;
        font-size:35px;
        font-weight:600;
        color:white;
    }

    .sidebar-brand small{
        color:#cbd5e1;
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

        .stat-card.red {
            background: linear-gradient(135deg, var(--danger) 0%, #fc8181 100%);
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

        .badge-success {
            background-color: var(--success);
            color: var(--white);
        }

        .badge-warning {
            background-color: var(--warning);
            color: var(--white);
        }

        .badge-danger {
            background-color: var(--danger);
            color: var(--white);
        }

        .badge-info {
            background-color: var(--info);
            color: var(--white);
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
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-tools"></i> Tracking Kondisi Aset</h2>
                <span><?= date('d F Y') ?></span>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card green">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Baik</h6>
                                <h3><?= $stat_baik ?></h3>
                            </div>
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card orange">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Rusak Ringan</h6>
                                <h3><?= $stat_rusak_ringan ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card red">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Rusak Berat</h6>
                                <h3><?= $stat_rusak_berat ?></h3>
                            </div>
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card blue">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Dalam Perbaikan</h6>
                                <h3><?= $stat_perbaikan ?></h3>
                            </div>
                            <i class="fas fa-wrench"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Kondisi Aset</h5>
                    <div class="search-box">
                        <form method="GET" class="d-flex">
                            <select name="filter" class="form-select me-2" style="width: 200px;">
                                <option value="all" <?= $filter=='all'?'selected':'' ?>>Semua Kondisi</option>
                                <option value="Baik" <?= $filter=='Baik'?'selected':'' ?>>Baik</option>
                                <option value="Rusak Ringan" <?= $filter=='Rusak Ringan'?'selected':'' ?>>Rusak Ringan</option>
                                <option value="Rusak Berat" <?= $filter=='Rusak Berat'?'selected':'' ?>>Rusak Berat</option>
                                <option value="Dalam Perbaikan" <?= $filter=='Dalam Perbaikan'?'selected':'' ?>>Dalam Perbaikan</option>
                            </select>

                            <input type="text" name="search" class="form-control me-2" placeholder="Cari aset..." value="<?= $search ?>" style="width: 250px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
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
                                    <th>Nama Barang</th>
                                    <th>Kategori</th>
                                    <th>Ruangan</th>
                                    <th>Kondisi</th>
                                    <th>Tanggal Cek</th>
                                    <th>Keterangan</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $currentCondition = $row['kondisi_aset'] ?? '-';
                                    if ($currentCondition == 'Baik') {
                                        $badgeClass = 'badge-success';
                                    } elseif ($currentCondition == 'Rusak Ringan') {
                                        $badgeClass = 'badge-warning';
                                    } elseif ($currentCondition == 'Dalam Perbaikan') {
                                        $badgeClass = 'badge-info';
                                    } elseif ($currentCondition == 'Rusak Berat') {
                                        $badgeClass = 'badge-danger';
                                    } elseif ($currentCondition == 'Tidak Layak Pakai') {
                                        $badgeClass = 'badge-danger';
                                    } else {
                                        $badgeClass = 'badge-primary';
                                    }
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><strong><?= $row['nama_barang_108'] ?></strong></td>
                                    <td>
                                        <span class="badge badge-primary"><?= $row['kategori_id'] ?: 'Umum' ?></span>
                                    </td>
                                    <td><?= $row['nama_ruangan'] ?? '-' ?></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= $currentCondition ?></span></td>
                                    <td><?= formatTanggal($row['last_tanggal_cek'] ?? $row['created_at']) ?></td>
                                    <td><?= substr($row['last_keterangan'] ?? '-', 0, 50) ?>...</td>
                                    <td>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#modalKondisi<?= $row['id'] ?>">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                        <a href="riwayat_kondisi.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-history"></i> Riwayat
                                        </a>
                                    </td>
                                </tr>

                                <!-- Modal Update Kondisi -->
                                <div class="modal fade" id="modalKondisi<?= $row['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Kondisi Aset</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="inventaris_id" value="<?= $row['id'] ?>">
                                                    <div class="mb-3">
                                                        <label class="form-label">Nama Barang</label>
                                                        <input type="text" class="form-control" value="<?= $row['nama_barang_108'] ?>" readonly>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Kondisi Aset *</label>
                                                        <select name="kondisi" class="form-select" required>
                                                            <option value="Baik" <?= $row['kondisi_aset']=='Baik'?'selected':'' ?>>Baik</option>
                                                            <option value="Rusak Ringan" <?= $row['kondisi_aset']=='Rusak Ringan'?'selected':'' ?>>Rusak Ringan</option>
                                                            <option value="Rusak Berat" <?= $row['kondisi_aset']=='Rusak Berat'?'selected':'' ?>>Rusak Berat</option>
                                                            <option value="Dalam Perbaikan" <?= $row['kondisi_aset']=='Dalam Perbaikan'?'selected':'' ?>>Dalam Perbaikan</option>
                                                            <option value="Tidak Layak Pakai" <?= $row['kondisi_aset']=='Tidak Layak Pakai'?'selected':'' ?>>Tidak Layak Pakai</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Keterangan</label>
                                                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Deskripsi kondisi..."><?= $last_update['keterangan'] ?? '' ?></textarea>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                    <button type="submit" name="update_kondisi" class="btn btn-primary">Simpan</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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