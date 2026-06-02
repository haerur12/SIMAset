<?php
require 'config.php';

$current_page = basename($_SERVER['PHP_SELF']);

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// ✅ TAMBAH TRACKING
if(isset($_POST['tambah_tracking'])) {
    $inventaris_id = (int)$_POST['inventaris_id'];
    $tanggal = $_POST['tanggal_tracking'];
    $jenis = mysqli_real_escape_string($conn, $_POST['jenis_tracking']);
    $dari = mysqli_real_escape_string($conn, $_POST['dari_lokasi']);
    $ke = mysqli_real_escape_string($conn, $_POST['ke_lokasi']);
    $ket = mysqli_real_escape_string($conn, $_POST['keterangan']);
    $petugas = $_SESSION['nama_lengkap'];
    
    mysqli_query($conn, "INSERT INTO tracking_aset SET
        inventaris_id = '$inventaris_id',
        tanggal_tracking = '$tanggal',
        jenis_tracking = '$jenis',
        dari_lokasi = '$dari',
        ke_lokasi = '$ke',
        keterangan = '$ket',
        petugas = '$petugas'
    ");
    
    // Jika jenis tracking adalah "Pindah Ruangan", update ruangan di tabel inventaris
    if($jenis == 'Pindah Ruangan' && !empty($ke)) {
        $ruangan_baru = mysqli_query($conn, "SELECT id FROM ruangan WHERE nama_ruangan = '$ke' LIMIT 1");
        if($ruangan_baru->num_rows > 0) {
            $r = $ruangan_baru->fetch_assoc();
            mysqli_query($conn, "UPDATE inventaris SET ruangan_id = {$r['id']} WHERE id = $inventaris_id");
        }
    }
    
    echo "<script>alert('Tracking aset berhasil ditambahkan!'); window.location='tracking_aset.php';</script>";
}

// ✅ HAPUS TRACKING
if(isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    mysqli_query($conn, "DELETE FROM tracking_aset WHERE id = $id");
    echo "<script>alert('Tracking berhasil dihapus!'); window.location='tracking_aset.php';</script>";
}

// ✅ PAGINATION & FILTER
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

$filter_jenis = isset($_GET['jenis']) ? $_GET['jenis'] : '';
$search = isset($_GET['cari']) ? $_GET['cari'] : '';

$where = [];
if($filter_jenis) {
    $where[] = "t.jenis_tracking = '$filter_jenis'";
}
if($search) {
    $where[] = "(i.nama_barang_108 LIKE '%$search%' OR t.keterangan LIKE '%$search%' OR t.petugas LIKE '%$search%')";
}
$where_clause = $where ? "WHERE " . implode(' AND ', $where) : "";

$query = "SELECT t.*, i.nama_barang_108, i.spesifikasi_nama_barang 
          FROM tracking_aset t 
          LEFT JOIN inventaris i ON t.inventaris_id = i.id 
          $where_clause 
          ORDER BY t.tanggal_tracking DESC, t.created_at DESC 
          LIMIT $start, $limit";

$result = mysqli_query($conn, $query);
$total_records = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset t LEFT JOIN inventaris i ON t.inventaris_id = i.id $where_clause")->fetch_assoc()['total'];
$total_pages = ceil($total_records / $limit);

// ✅ STATISTIK
$total_tracking = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset")->fetch_assoc()['total'];
$total_pindah = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset WHERE jenis_tracking = 'Pindah Ruangan'")->fetch_assoc()['total'];
$total_mutasi = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset WHERE jenis_tracking = 'Mutasi'")->fetch_assoc()['total'];
$total_pinjam = mysqli_query($conn, "SELECT COUNT(*) as total FROM tracking_aset WHERE jenis_tracking = 'Peminjaman'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Aset - Inventaris SDN Curug 01</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a365d;
            --primary-dark: #0f2744;
            --primary-light: #2d4a7c;
            --white: #ffffff;
            --gray-light: #f7fafc;
            --gray-medium: #e2e8f0;
            --gray-dark: #4a5568;
            --success: #38a169;
            --warning: #d69e2e;
            --danger: #e53e3e;
            --info: #4299e1;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--gray-light);
            color: var(--gray-dark);
            line-height: 1.6;
        }

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

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26, 54, 93, 0.1);
        }

        .search-box {
            display: flex;
            gap: 10px;
        }

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
                <h2><i class="fas fa-route"></i> Tracking Aset</h2>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalTambah">
                    <i class="fas fa-plus"></i> Tambah Tracking
                </button>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card blue">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Total Tracking</h6>
                                <h3><?= $total_tracking ?></h3>
                            </div>
                            <i class="fas fa-route"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card green">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Pindah Ruangan</h6>
                                <h3><?= $total_pindah ?></h3>
                            </div>
                            <i class="fas fa-exchange-alt"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card orange">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Mutasi</h6>
                                <h3><?= $total_mutasi ?></h3>
                            </div>
                            <i class="fas fa-random"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card red">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Peminjaman</h6>
                                <h3><?= $total_pinjam ?></h3>
                            </div>
                            <i class="fas fa-hand-holding"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Riwayat Tracking Aset</h5>
                    <div class="search-box">
                        <form method="GET" class="d-flex">
                            <select name="jenis" class="form-select me-2" style="width: 180px;">
                                <option value="">Semua Jenis</option>
                                <option value="Pindah Ruangan" <?= $filter_jenis=='Pindah Ruangan'?'selected':'' ?>>Pindah Ruangan</option>
                                <option value="Mutasi" <?= $filter_jenis=='Mutasi'?'selected':'' ?>>Mutasi</option>
                                <option value="Peminjaman" <?= $filter_jenis=='Peminjaman'?'selected':'' ?>>Peminjaman</option>
                                <option value="Pengembalian" <?= $filter_jenis=='Pengembalian'?'selected':'' ?>>Pengembalian</option>
                                <option value="Perbaikan" <?= $filter_jenis=='Perbaikan'?'selected':'' ?>>Perbaikan</option>
                            </select>
                            <input type="text" name="cari" class="form-control me-2" placeholder="Cari aset..." value="<?= htmlspecialchars($search) ?>">
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
                                    <th>Tanggal</th>
                                    <th>Nama Aset</th>
                                    <th>Jenis Tracking</th>
                                    <th>Dari</th>
                                    <th>Ke</th>
                                    <th>Petugas</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $no = $start + 1;
                                while($row = mysqli_fetch_assoc($result)): 
                                    $badgeClass = match($row['jenis_tracking']) {
                                        'Pindah Ruangan' => 'badge-success',
                                        'Mutasi' => 'badge-warning',
                                        'Peminjaman' => 'badge-info',
                                        'Pengembalian' => 'badge-primary',
                                        'Perbaikan' => 'badge-danger',
                                        default => 'badge-primary'
                                    };
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= date('d/m/Y', strtotime($row['tanggal_tracking'])) ?></td>
                                    <td><strong><?= $row['nama_barang_108'] ?></strong></td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= $row['jenis_tracking'] ?></span></td>
                                    <td><?= $row['dari_lokasi'] ?: '-' ?></td>
                                    <td><?= $row['ke_lokasi'] ?: '-' ?></td>
                                    <td><?= $row['petugas'] ?></td>
                                    <td>
                                        <a href="?hapus=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus tracking ini?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                
                                <?php if(mysqli_num_rows($result) == 0): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        Belum ada data tracking.
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
                                <a class="page-link" href="?page=<?= $i ?>&jenis=<?= $filter_jenis ?>&cari=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Tracking -->
<div class="modal fade" id="modalTambah" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header" style="background-color: var(--primary); color: white;">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Tambah Tracking Aset</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Pilih Aset *</label>
                            <select name="inventaris_id" class="form-select" required>
                                <option value="">-- Pilih Aset --</option>
                                <?php
                                $asets = mysqli_query($conn, "SELECT id, nama_barang_108 FROM inventaris ORDER BY nama_barang_108 ASC");
                                while($a = mysqli_fetch_assoc($asets)):
                                ?>
                                <option value="<?= $a['id'] ?>"><?= $a['nama_barang_108'] ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal *</label>
                            <input type="date" name="tanggal_tracking" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Jenis Tracking *</label>
                        <select name="jenis_tracking" class="form-select" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="Pindah Ruangan">Pindah Ruangan</option>
                            <option value="Mutasi">Mutasi</option>
                            <option value="Peminjaman">Peminjaman</option>
                            <option value="Pengembalian">Pengembalian</option>
                            <option value="Perbaikan">Perbaikan</option>
                        </select>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Dari Lokasi</label>
                            <input type="text" name="dari_lokasi" class="form-control" placeholder="Contoh: Ruang Kelas 1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ke Lokasi</label>
                            <input type="text" name="ke_lokasi" class="form-control" placeholder="Contoh: Ruang Guru">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="keterangan" class="form-control" rows="3" placeholder="Catatan tambahan..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" name="tambah_tracking" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>