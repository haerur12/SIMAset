<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

if(isset($_POST['simpan'])) {
    // Kategori Aset (baru)
    $kategori_id = isset($_POST['kategori_id']) ? mysqli_real_escape_string($conn, $_POST['kategori_id']) : '';
    // Informasi Lokasi
    $kode_lokasi = $_POST['kode_lokasi'];
    $nama_unit_lokasi = $_POST['nama_unit_lokasi'];
    
    // Informasi Pengadaan
    $sumber_pengadaan = $_POST['sumber_pengadaan'];
    $bentuk_kontrak = $_POST['bentuk_kontrak'];
    $no_dokumen_kontrak = $_POST['no_dokumen_kontrak'];
    $tanggal_kontrak = $_POST['tanggal_kontrak'];
    $pihak_ke_3 = $_POST['pihak_ke_3'];
    $no_bast = $_POST['no_bast'];
    $tanggal_bast = $_POST['tanggal_bast'];
    
    // Informasi Pejabat
    $nama_ppk = $_POST['nama_ppk'];
    $nama_pengurus_barang = $_POST['nama_pengurus_barang'];
    $no_surat_pernyataan = $_POST['no_surat_pernyataan'];
    $tanggal_pernyataan = $_POST['tanggal_pernyataan'];
    
    // Informasi Kegiatan & Rekening
    $kode_sub_kegiatan = $_POST['kode_sub_kegiatan'];
    $nama_sub_kegiatan = $_POST['nama_sub_kegiatan'];
    $kode_rekening_belanja = $_POST['kode_rekening_belanja'];
    $nama_rekening_belanja = $_POST['nama_rekening_belanja'];
    
    // Informasi Barang
    $kode_barang_108 = $_POST['kode_barang_108'];
    $nama_barang_108 = $_POST['nama_barang_108'];
    $spesifikasi_nama_barang = $_POST['spesifikasi_nama_barang'];
    $satuan = $_POST['satuan'];
    $jumlah = $_POST['jumlah'];
    $harga_satuan = $_POST['harga_satuan'];
    $total = $jumlah * $harga_satuan;
    
    // Tambahan
    $judul = $_POST['judul'];
    $pencipta = $_POST['pencipta'];
    $keterangan = $_POST['keterangan'];
    
    $query = "INSERT INTO inventaris SET
        kode_lokasi = '$kode_lokasi',
        nama_unit_lokasi = '$nama_unit_lokasi',
        sumber_pengadaan = '$sumber_pengadaan',
        bentuk_kontrak = '$bentuk_kontrak',
        no_dokumen_kontrak = '$no_dokumen_kontrak',
        tanggal_kontrak = '$tanggal_kontrak',
        pihak_ke_3 = '$pihak_ke_3',
        no_bast = '$no_bast',
        tanggal_bast = '$tanggal_bast',
        nama_ppk = '$nama_ppk',
        nama_pengurus_barang = '$nama_pengurus_barang',
        no_surat_pernyataan = '$no_surat_pernyataan',
        tanggal_pernyataan = '$tanggal_pernyataan',
        kode_sub_kegiatan = '$kode_sub_kegiatan',
        nama_sub_kegiatan = '$nama_sub_kegiatan',
        kode_rekening_belanja = '$kode_rekening_belanja',
        nama_rekening_belanja = '$nama_rekening_belanja',
        kategori_id = '$kategori_id',
        kode_barang_108 = '$kode_barang_108',
        nama_barang_108 = '$nama_barang_108',
        spesifikasi_nama_barang = '$spesifikasi_nama_barang',
        satuan = '$satuan',
        jumlah = '$jumlah',
        harga_satuan = '$harga_satuan',
        total = '$total',
        judul = '$judul',
        pencipta = '$pencipta',
        keterangan = '$keterangan'
    ";
    
    if(mysqli_query($conn, $query)) {
        echo "<script>alert('Data berhasil ditambahkan!'); window.location='dashboard.php';</script>";
    } else {
        echo "<script>alert('Gagal menambah data: " . mysqli_error($conn) . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Aset - Inventaris SDN Curug 1</title>
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
            color: #1e3c72;
            border-bottom: 3px solid #2a5298;
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .btn-primary { 
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); 
            border: none; 
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2a5298 0%, #1e3c72 100%);
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="fas fa-plus-circle"></i> Tambah Aset Baru</h3>
        <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>

    <form method="POST">
        <!-- Informasi Lokasi -->
      
                    <div class="form-section">
                        <h5 class="section-title"><i class="fas fa-map-marker-alt"></i> Informasi Lokasi</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pilih Ruangan *</label>
                                <select name="ruangan_id" id="ruangan_id" class="form-select" onchange="autoFillLokasi()" required>
                                    <option value="">-- Pilih Ruangan --</option>
                                    <?php
                                    $ruangan = mysqli_query($conn, "SELECT * FROM ruangan ORDER BY nama_ruangan ASC");
                                    while($r = mysqli_fetch_assoc($ruangan)):
                                    ?>
                                    <option value="<?= $r['id'] ?>" 
                                            data-kode="<?= $r['kode_ruangan'] ?>" 
                                            data-nama="<?= $r['nama_ruangan'] ?>">
                                        <?= $r['kode_ruangan'] ?> - <?= $r['nama_ruangan'] ?> (<?= $r['gedung'] ?>)
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Kode Lokasi</label>
                                <input type="text" name="kode_lokasi" id="kode_lokasi" class="form-control" value="01.00.00.0055" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Nama Unit/Lokasi</label>
                                <input type="text" name="nama_unit_lokasi" id="nama_unit_lokasi" class="form-control" value="UPTD SDN Curug 1 (Bojongsari)" required>
                            </div>
                        </div>
                    </div>

                    <script>
                    function autoFillLokasi() {
                        const select = document.getElementById('ruangan_id');
                        const option = select.options[select.selectedIndex];
                        const namaRuangan = option.getAttribute('data-nama');
                        
                        if(namaRuangan) {
                            document.getElementById('nama_unit_lokasi').value = 'UPTD SDN Curug 1 - ' + namaRuangan;
                        }
                    }
                    </script>

        <!-- Informasi Pengadaan -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-file-contract"></i> Informasi Pengadaan</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sumber Pengadaan *</label>
                    <select name="sumber_pengadaan" class="form-select" required>
                        <option value="">Pilih Sumber</option>
                        <option value="Pemerintah">Pemerintah</option>
                        <option value="Sekolah">Sekolah</option>
                        <option value="BOS">BOS</option>
                        <option value="DAK">DAK</option>
                        <option value="APBD">APBD</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Bentuk Kontrak</label>
                    <select name="bentuk_kontrak" class="form-select">
                        <option value="">Pilih</option>
                        <option value="Surat Pesanan">Surat Pesanan</option>
                        <option value="Kontrak">Kontrak</option>
                        <option value="SPK">SPK</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">No Dokumen Kontrak</label>
                    <input type="text" name="no_dokumen_kontrak" class="form-control">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Kontrak</label>
                    <input type="date" name="tanggal_kontrak" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Pihak ke-3 (Vendor)</label>
                    <input type="text" name="pihak_ke_3" class="form-control">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">No BAST</label>
                    <input type="text" name="no_bast" class="form-control">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal BAST</label>
                    <input type="date" name="tanggal_bast" class="form-control">
                </div>
            </div>
        </div>

        <!-- Informasi Pejabat -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-user-tie"></i> Informasi Pejabat</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nama PPK</label>
                    <input type="text" name="nama_ppk" class="form-control" value="CECEP ROHAEDI, S.Pd">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nama Pengurus Barang</label>
                    <input type="text" name="nama_pengurus_barang" class="form-control" value="MIHARYATI, S.Pd">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">No Surat Pernyataan</label>
                    <input type="text" name="no_surat_pernyataan" class="form-control">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Tanggal Pernyataan</label>
                    <input type="date" name="tanggal_pernyataan" class="form-control">
                </div>
            </div>
        </div>

        <!-- Informasi Kegiatan & Rekening -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-tasks"></i> Informasi Kegiatan & Rekening</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Kode Sub Kegiatan</label>
                    <input type="text" name="kode_sub_kegiatan" class="form-control">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nama Sub Kegiatan</label>
                    <input type="text" name="nama_sub_kegiatan" class="form-control">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Kode Rekening Belanja</label>
                    <input type="text" name="kode_rekening_belanja" class="form-control" value="5.1.02.88.88.8888">
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nama Rekening Belanja</label>
                    <input type="text" name="nama_rekening_belanja" class="form-control" value="Belanja Barang dan Jasa BOS">
                </div>
            </div>
        </div>

        <!-- Informasi Barang -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-box"></i> Informasi Barang (Permendagri 108)</h5>
                <!-- ✅ DROPDOWN 7 KATEGORI UTAMA -->
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kategori Aset *</label>
                        <select name="kategori_id" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <option value="Buku & Bahan Ajar">Buku & Bahan Ajar</option>
                            <option value="Alat Tulis Kantor (ATK)">Alat Tulis Kantor (ATK)</option>
                            <option value="Perlengkapan Komputer & Printer">Perlengkapan Komputer & Printer</option>
                            <option value="Perlengkapan Kebersihan">Perlengkapan Kebersihan</option>
                            <option value="Perlengkapan Kesehatan">Perlengkapan Kesehatan</option>
                            <option value="Peralatan Olahraga">Peralatan Olahraga</option>
                            <option value="Peralatan dan Sarana Pendukung Sekolah">Peralatan dan Sarana Pendukung Sekolah</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3"></div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Kode Barang (108)</label>
                        <input type="text" name="kode_barang_108" class="form-control">
                    </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label">Nama Barang (Permendagri 108) *</label>
                    <input type="text" name="nama_barang_108" class="form-control" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Spesifikasi Nama Barang *</label>
                <textarea name="spesifikasi_nama_barang" class="form-control" rows="3" required></textarea>
            </div>
            
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Satuan *</label>
                    <input type="text" name="satuan" class="form-control" placeholder="Unit/Buah/Set" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Jumlah *</label>
                    <input type="number" name="jumlah" id="jumlah" class="form-control" required onchange="hitungTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Harga Satuan *</label>
                    <input type="number" name="harga_satuan" id="harga_satuan" class="form-control" required onchange="hitungTotal()">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Total</label>
                    <input type="text" name="total" id="total" class="form-control" readonly>
                </div>
            </div>
        </div>

        <!-- Informasi Tambahan -->
        <div class="form-section">
            <h5 class="section-title"><i class="fas fa-info-circle"></i> Informasi Tambahan</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Judul</label>
                    <input type="text" name="judul" class="form-control">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Pencipta</label>
                    <input type="text" name="pencipta" class="form-control">
                </div>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Keterangan</label>
                <textarea name="keterangan" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="simpan" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
        </div>
    </form>
</div>

<script>
function hitungTotal() {
    const jumlah = document.getElementById('jumlah').value;
    const harga = document.getElementById('harga_satuan').value;
    const total = jumlah * harga;
    document.getElementById('total').value = total.toLocaleString('id-ID');
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>