<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Update kondisi
if(isset($_POST['update_kondisi'])) {
    $inventaris_id = $_POST['inventaris_id'];
    $kondisi = $_POST['kondisi'];
    $keterangan = $_POST['keterangan'];
    $petugas = $_SESSION['nama_lengkap'];
    $tanggal_cek = date('Y-m-d');
    
    // Update kondisi di tabel inventaris
    mysqli_query($conn, "UPDATE inventaris SET kondisi_aset = '$kondisi' WHERE id = $inventaris_id");
    
    // Insert ke history kondisi
    mysqli_query($conn, "INSERT INTO kondisi_aset SET
        inventaris_id = '$inventaris_id',
        kondisi = '$kondisi',
        tanggal_cek = '$tanggal_cek',
        keterangan = '$keterangan',
        petugas = '$petugas'
    ");
    
    echo "<script>alert('Kondisi aset berhasil diupdate!'); window.location='kondisi_aset.php';</script>";
}

// Filter kondisi
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$where = "";
if($filter != 'all') {
    $where = "WHERE i.kondisi_aset = '$filter'";
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

$query = "SELECT i.*, r.nama_ruangan 
          FROM inventaris i 
          LEFT JOIN ruangan r ON i.ruangan_id = r.id 
          $where 
          ORDER BY i.created_at DESC";
$result = mysqli_query($conn, $query);

// Statistik kondisi
$stat_baik = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE kondisi_aset = 'Baik'")->fetch_assoc()['total'];
$stat_rusak_ringan = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE kondisi_aset = 'Rusak Ringan'")->fetch_assoc()['total'];
$stat_rusak_berat = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE kondisi_aset = 'Rusak Berat'")->fetch_assoc()['total'];
$stat_perbaikan = mysqli_query($conn, "SELECT COUNT(*) as total FROM inventaris WHERE kondisi_aset = 'Dalam Perbaikan'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kondisi Aset - Inventaris SDN Curug 01</title>
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
                <h2><i class="fas fa-tools"></i> Tracking Kondisi Aset</h2>
                <span><?= date('d F Y') ?></span>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #38a169 0%, #48bb78 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Baik</h6>
                                <h3><?= $stat_baik ?></h3>
                            </div>
                            <i class="fas fa-check-circle fa-3x" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #d69e2e 0%, #ecc94b 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Rusak Ringan</h6>
                                <h3><?= $stat_rusak_ringan ?></h3>
                            </div>
                            <i class="fas fa-exclamation-triangle fa-3x" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #e53e3e 0%, #fc8181 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Rusak Berat</h6>
                                <h3><?= $stat_rusak_berat ?></h3>
                            </div>
                            <i class="fas fa-times-circle fa-3x" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #4299e1 0%, #63b3ed 100%);">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6>Dalam Perbaikan</h6>
                                <h3><?= $stat_perbaikan ?></h3>
                            </div>
                            <i class="fas fa-wrench fa-3x" style="opacity: 0.3;"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter & Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Filter Kondisi</label>
                            <select name="filter" class="form-select">
                                <option value="all" <?= $filter=='all'?'selected':'' ?>>Semua Kondisi</option>
                                <option value="Baik" <?= $filter=='Baik'?'selected':'' ?>>Baik</option>
                                <option value="Rusak Ringan" <?= $filter=='Rusak Ringan'?'selected':'' ?>>Rusak Ringan</option>
                                <option value="Rusak Berat" <?= $filter=='Rusak Berat'?'selected':'' ?>>Rusak Berat</option>
                                <option value="Dalam Perbaikan" <?= $filter=='Dalam Perbaikan'?'selected':'' ?>>Dalam Perbaikan</option>
                                <option value="Tidak Layak Pakai" <?= $filter=='Tidak Layak Pakai'?'selected':'' ?>>Tidak Layak Pakai</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cari Aset</label>
                            <input type="text" name="search" class="form-control" placeholder="Nama barang..." value="<?= $search ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Daftar Kondisi Aset
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Nama Barang</th>
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
                                    $badgeClass = $row['kondisi_aset'] == 'Baik' ? 'badge-success' : 
                                                 ($row['kondisi_aset'] == 'Rusak Ringan' ? 'badge-warning' : 
                                                 ($row['kondisi_aset'] == 'Dalam Perbaikan' ? 'badge-info' : 'badge-danger'));
                                    
                                    // Get last kondisi update
                                    $last_update = mysqli_query($conn, "SELECT * FROM kondisi_aset WHERE inventaris_id = {$row['id']} ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
                                ?>
                                <tr>
                                    <td><?= $no++ ?></td>
                                    <td><?= $row['nama_barang_108'] ?></td>
                                   <td>-</td>
                                    <td><span class="badge <?= $badgeClass ?>"><?= $row['kondisi_aset'] ?></span></td>
                                    <td><?= formatTanggal($last_update['tanggal_cek'] ?? $row['created_at']) ?></td>
                                    <td><?= substr($last_update['keterangan'] ?? '-', 0, 50) ?>...</td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalKondisi<?= $row['id'] ?>">
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