

-- Tabel Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama_lengkap VARCHAR(100),
    level ENUM('admin', 'staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert user default (username: admin, password: admin123)
INSERT INTO users (username, password, nama_lengkap, level) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Tabel Inventaris
CREATE TABLE inventaris (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kode_lokasi VARCHAR(50),
    nama_unit_lokasi VARCHAR(100),
    sumber_pengadaan ENUM('Pemerintah', 'Sekolah', 'BOS', 'DAK') NOT NULL,
    bentuk_kontrak VARCHAR(50),
    jenis_bukti_pembelian VARCHAR(100),
    no_dokumen_kontrak VARCHAR(50),
    tanggal_kontrak DATE,
    pihak_ke_3 VARCHAR(100),
    no_bast VARCHAR(50),
    tanggal_bast DATE,
    sumber_dana VARCHAR(50),
    nama_ppk VARCHAR(100),
    nama_pengurus_barang VARCHAR(100),
    no_surat_pernyataan VARCHAR(50),
    tanggal_pernyataan DATE,
    kode_sub_kegiatan VARCHAR(50),
    nama_sub_kegiatan VARCHAR(150),
    kode_rekening_belanja VARCHAR(50),
    nama_rekening_belanja VARCHAR(150),
    kode_barang_108 VARCHAR(50),
    nama_barang_108 VARCHAR(150),
    nusp VARCHAR(50),
    spesifikasi_nama_barang TEXT,
    satuan VARCHAR(20),
    jumlah INT DEFAULT 1,
    harga_satuan DECIMAL(15,2),
    total DECIMAL(15,2),
    keterangan TEXT,
    pph DECIMAL(15,2) DEFAULT 0,
    ppn DECIMAL(15,2) DEFAULT 0,
    jenis_belanja VARCHAR(50),
    jumlah_sisa_persediaan INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Data contoh
INSERT INTO inventaris (
    kode_lokasi, nama_unit_lokasi, sumber_pengadaan, spesifikasi_nama_barang, 
    satuan, jumlah, harga_satuan, total, nama_barang_108
) VALUES 
('01.00.00.0001', 'SDN Curug 1', 'Pemerintah', 'Meja Kayu Jati Ukuran 120x60cm', 'Unit', 10, 1500000, 15000000, 'Meja Kerja'),
('01.00.00.0001', 'SDN Curug 1', 'Sekolah', 'Kursi Plastik Standard', 'Unit', 30, 150000, 4500000, 'Kursi');