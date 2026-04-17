<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
$data = mysqli_query($conn, "SELECT * FROM ruangan WHERE id = $id")->fetch_assoc();

if(isset($_POST['update'])) {
    $kode_ruangan = $_POST['kode_ruangan'];
    $nama_ruangan = $_POST['nama_ruangan'];
    $lantai = $_POST['lantai'];
    $gedung = $_POST['gedung'];
    $kapasitas = $_POST['kapasitas'];
    $fungsi_ruangan = $_POST['fungsi_ruangan'];
    $penanggung_jawab = $_POST['penanggung_jawab'];
    $keterangan = $_POST['keterangan'];
    
    $query = "UPDATE ruangan SET
        kode_ruangan = '$kode_ruangan',
        nama_ruangan = '$nama_ruangan',
        lantai = '$lantai',
        gedung = '$gedung',
        kapasitas = '$kapasitas',
        fungsi_ruangan = '$fungsi_ruangan',
        penanggung_jawab = '$penanggung_jawab',
        keterangan = '$keterangan'
        WHERE id = $id
    ";
    
    if(mysqli_query($conn, $query)) {
        echo "<script>alert('Ruangan berhasil diupdate!'); window.location='ruangan.php';</script>";
    } else {
        echo "<script>alert('Gagal update: " . mysqli_error($conn) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Edit Ruangan - Inventaris</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .form-section { 
            background: white; 
            padding: 30px; 
            border-radius: 10px; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .section-title {
            color: #f39c12;
            border-bottom: 3px solid #e67e22;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .btn-warning { 
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); 
            border: none; 
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-edit"></i> Edit Ruangan</h3>
        <a href="ruangan.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <div class="form-section">
        <form method="POST">
            <h5 class="section-title"><i class="fas fa-info-circle"></i> Informasi Dasar</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Kode Ruangan *</label>
                    <input type="text" name="kode_ruangan" class="form-control" value="<?= $data['kode_ruangan'] ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nama Ruangan *</label>
                    <input type="text" name="nama_ruangan" class="form-control" value="<?= $data['nama_ruangan'] ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Gedung *</label>
                    <select name="gedung" class="form-select" required>
                        <option value="Gedung A" <?= $data['gedung']=='Gedung A'?'selected':'' ?>>Gedung A</option>
                        <option value="Gedung B" <?= $data['gedung']=='Gedung B'?'selected':'' ?>>Gedung B</option>
                        <option value="Gedung C" <?= $data['gedung']=='Gedung C'?'selected':'' ?>>Gedung C</option>
                        <option value="Gedung D" <?= $data['gedung']=='Gedung D'?'selected':'' ?>>Gedung D</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Lantai *</label>
                    <select name="lantai" class="form-select" required>
                        <option value="1" <?= $data['lantai']=='1'?'selected':'' ?>>Lantai 1</option>
                        <option value="2" <?= $data['lantai']=='2'?'selected':'' ?>>Lantai 2</option>
                        <option value="3" <?= $data['lantai']=='3'?'selected':'' ?>>Lantai 3</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Kapasitas (Orang)</label>
                    <input type="number" name="kapasitas" class="form-control" value="<?= $data['kapasitas'] ?>">
                </div>
            </div>

            <h5 class="section-title mt-4"><i class="fas fa-clipboard-list"></i> Informasi Tambahan</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Fungsi Ruangan</label>
                    <select name="fungsi_ruangan" class="form-select">
                        <option value="">Pilih Fungsi</option>
                        <option value="Kegiatan Belajar Mengajar" <?= $data['fungsi_ruangan']=='Kegiatan Belajar Mengajar'?'selected':'' ?>>Kegiatan Belajar Mengajar</option>
                        <option value="Ruang Kerja Guru" <?= $data['fungsi_ruangan']=='Ruang Kerja Guru'?'selected':'' ?>>Ruang Kerja Guru</option>
                        <option value="Praktikum" <?= $data['fungsi_ruangan']=='Praktikum'?'selected':'' ?>>Praktikum</option>
                        <option value="Perpustakaan" <?= $data['fungsi_ruangan']=='Perpustakaan'?'selected':'' ?>>Perpustakaan</option>
                        <option value="Kesehatan" <?= $data['fungsi_ruangan']=='Kesehatan'?'selected':'' ?>>Kesehatan</option>
                        <option value="Ibadah" <?= $data['fungsi_ruangan']=='Ibadah'?'selected':'' ?>>Ibadah</option>
                        <option value="Olahraga" <?= $data['fungsi_ruangan']=='Olahraga'?'selected':'' ?>>Olahraga</option>
                        <option value="Lainnya" <?= $data['fungsi_ruangan']=='Lainnya'?'selected':'' ?>>Lainnya</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Penanggung Jawab</label>
                    <input type="text" name="penanggung_jawab" class="form-control" value="<?= $data['penanggung_jawab'] ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="3"><?= $data['keterangan'] ?></textarea>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" name="update" class="btn btn-warning"><i class="fas fa-save"></i> Update</button>
                <a href="ruangan.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
            </div>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>