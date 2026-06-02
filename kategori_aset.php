<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ HAPUS KATEGORI
if(isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM kategori_aset WHERE id = $id");
    echo "<script>alert('Kategori berhasil dihapus!'); window.location='kategori_aset.php';</script>";
}

// ✅ PAGINATION
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// ✅ SEARCH
$search = isset($_GET['cari']) ? $_GET['cari'] : '';
$where = "";
if($search) {
    $where = "WHERE nama_kategori LIKE '%$search%' OR kode_kategori LIKE '%$search%'";
}

$result = mysqli_query($conn, "SELECT * FROM kategori_aset $where ORDER BY created_at DESC LIMIT $start, $limit");
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM kategori_aset $where")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// ✅ STATISTIK
$total_kategori = mysqli_query($conn, "SELECT COUNT(*) as total FROM kategori_aset")->fetch_assoc()['total'];

// View assets for a category (when requested)
$view_id = isset($_GET['view_id']) ? (int)$_GET['view_id'] : 0;
$view_cat = null;
$inventaris_by_cat = null;
if($view_id) {
    $view_cat = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM kategori_aset WHERE id = $view_id"));
    if($view_cat) {
        $catNameEsc = mysqli_real_escape_string($conn, $view_cat['nama_kategori']);
        $inventaris_by_cat = mysqli_query($conn, "SELECT i.*, r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id WHERE i.kategori_id = '$catNameEsc' ORDER BY i.created_at DESC");
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kategori Aset - Inventaris SDN Curug 01</title>
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

        .sidebar-logo {
            width: 120px;
            height: 120px;
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

        .sidebar-brand h4 {
            margin-top: 10px;
            font-size: 24px;
            font-weight: 600;
            color: var(--white);
        }

        .sidebar-brand h6 {
            color: #cbd5e1;
            margin-bottom: 0;
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
        <!-- ✅ SIDEBAR (Menggunakan include agar sama persis dengan ruangan.php) -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
            <div class="page-header">
                <h2><i class="fas fa-tags"></i> Manajemen Kategori Aset</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus"></i> Tambah Kategori
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card blue">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Total Kategori</h6>
                                <h3><?= $total_kategori ?></h3>
                            </div>
                            <i class="fas fa-tags"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Daftar Kategori</h5>
                    <div class="search-box">
                        <form method="GET" class="d-flex">
                            <input type="text" name="cari" class="form-control me-2" placeholder="Cari kategori..." value="<?= htmlspecialchars($search) ?>">
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
                                    <th>Kode Kategori</th>
                                    <th>Nama Kategori</th>
                                    <th>Keterangan</th>
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
                                    <td><span class="badge badge-primary"><?= htmlspecialchars($row['kode_kategori']) ?></span></td>
                                    <td><strong><?= htmlspecialchars($row['nama_kategori']) ?></strong></td>
                                    <td><?= htmlspecialchars($row['keterangan']) ?: '-' ?></td>
                                    <td>
                                        <a href="?view_id=<?= $row['id'] ?>" class="btn btn-sm btn-info" title="Lihat Aset">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus kategori ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if(mysqli_num_rows($result) == 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        <?= $search ? 'Hasil pencarian tidak ditemukan.' : 'Belum ada data kategori.' ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
            </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <nav>
                        <ul class="pagination justify-content-end">
                            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&cari=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Daftar Aset untuk Kategori yang Dipilih -->
        <?php if($inventaris_by_cat): ?>
        <div class="col-md-12 mt-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-boxes"></i> Aset dalam Kategori: <?= htmlspecialchars($view_cat['nama_kategori']) ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
                                    <th>Ruangan</th>
                                    <th>Jumlah</th>
                                    <th>Harga Satuan</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $n=1; while($it = mysqli_fetch_assoc($inventaris_by_cat)): ?>
                                <tr>
                                    <td><?= $n++ ?></td>
                                    <td><strong><?= htmlspecialchars($it['nama_barang_108']) ?></strong></td>
                                    <td><?= htmlspecialchars($it['nama_ruangan'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($it['jumlah']) ?></td>
                                    <td><?= htmlspecialchars(number_format($it['harga_satuan'],0,',','.')) ?></td>
                                    <td><?= htmlspecialchars(number_format($it['total'],0,',','.')) ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ✅ MODAL TAMBAH KATEGORI -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header" style="background-color: var(--primary); color: white;">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Kategori Aset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Kode Kategori *</label>
                        <input type="text" name="kode_kategori" class="form-control" required placeholder="Contoh: ELK, MEB, KEND">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nama Kategori *</label>
                        <input type="text" name="nama_kategori" class="form-control" required placeholder="Contoh: Elektronik, Mebelair">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Deskripsi singkat kategori..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_kategori" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>