-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 17 Apr 2026 pada 07.28
-- Versi server: 12.2.2-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `db_inventaris_sekolah`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `inventaris`
--

CREATE TABLE `inventaris` (
  `id` int(11) NOT NULL,
  `kode_lokasi` varchar(50) DEFAULT NULL,
  `nama_unit_lokasi` varchar(100) DEFAULT NULL,
  `ruangan_id` int(11) DEFAULT NULL,
  `sumber_pengadaan` enum('Pemerintah','Sekolah','BOS','DAK') NOT NULL,
  `bentuk_kontrak` varchar(50) DEFAULT NULL,
  `no_dokumen_kontrak` varchar(50) DEFAULT NULL,
  `tanggal_kontrak` date DEFAULT NULL,
  `pihak_ke_3` varchar(100) DEFAULT NULL,
  `no_bast` varchar(50) DEFAULT NULL,
  `tanggal_bast` date DEFAULT NULL,
  `nama_ppk` varchar(100) DEFAULT NULL,
  `nama_barang_108` varchar(150) DEFAULT NULL,
  `spesifikasi_nama_barang` text DEFAULT NULL,
  `satuan` varchar(20) DEFAULT NULL,
  `jumlah` int(11) DEFAULT 1,
  `harga_satuan` decimal(15,2) DEFAULT NULL,
  `total` decimal(15,2) DEFAULT NULL,
  `judul` varchar(150) DEFAULT NULL,
  `pencipta` varchar(150) DEFAULT NULL,
  `sumber_dana` varchar(50) DEFAULT NULL,
  `nama_pengurus_barang` varchar(100) DEFAULT NULL,
  `no_surat_pernyataan` varchar(50) DEFAULT NULL,
  `tanggal_pernyataan` date DEFAULT NULL,
  `kode_sub_kegiatan` varchar(50) DEFAULT NULL,
  `nama_sub_kegiatan` varchar(150) DEFAULT NULL,
  `kode_rekening_belanja` varchar(50) DEFAULT NULL,
  `nama_rekening_belanja` varchar(150) DEFAULT NULL,
  `kode_barang_108` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `inventaris`
--

INSERT INTO `inventaris` (`id`, `kode_lokasi`, `nama_unit_lokasi`, `ruangan_id`, `sumber_pengadaan`, `bentuk_kontrak`, `no_dokumen_kontrak`, `tanggal_kontrak`, `pihak_ke_3`, `no_bast`, `tanggal_bast`, `nama_ppk`, `nama_barang_108`, `spesifikasi_nama_barang`, `satuan`, `jumlah`, `harga_satuan`, `total`, `judul`, `pencipta`, `sumber_dana`, `nama_pengurus_barang`, `no_surat_pernyataan`, `tanggal_pernyataan`, `kode_sub_kegiatan`, `nama_sub_kegiatan`, `kode_rekening_belanja`, `nama_rekening_belanja`, `kode_barang_108`, `created_at`, `updated_at`) VALUES
(1, '01.00.00.0055', 'SDN Curug 1', NULL, 'Pemerintah', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'BUKU ILMU BAHASA', 'Buku Siswa Bahasa Inggris', 'Buah', 32, 60.00, 1920.00, NULL, NULL, 'Pengadaan APBD', 'Aurora', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-04-16 09:28:01', '2026-04-16 09:28:01');

-- --------------------------------------------------------

--
-- Struktur dari tabel `kondisi_aset`
--

CREATE TABLE `kondisi_aset` (
  `id` int(11) NOT NULL,
  `inventaris_id` int(11) NOT NULL,
  `kondisi` enum('Baik','Rusak Ringan','Rusak Berat','Dalam Perbaikan','Tidak Layak Pakai') DEFAULT 'Baik',
  `tanggal_cek` date DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `petugas` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ruangan`
--

CREATE TABLE `ruangan` (
  `id` int(11) NOT NULL,
  `kode_ruangan` varchar(50) NOT NULL,
  `nama_ruangan` varchar(100) NOT NULL,
  `lantai` varchar(20) DEFAULT NULL,
  `gedung` varchar(100) DEFAULT NULL,
  `kapasitas` int(11) DEFAULT NULL,
  `fungsi_ruangan` varchar(100) DEFAULT NULL,
  `penanggung_jawab` varchar(100) DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `ruangan`
--

INSERT INTO `ruangan` (`id`, `kode_ruangan`, `nama_ruangan`, `lantai`, `gedung`, `kapasitas`, `fungsi_ruangan`, `penanggung_jawab`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'R-001', 'Kelas 1A', '1', 'Gedung A', 30, 'Kegiatan Belajar Mengajar', 'Guru Kelas 1A', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(2, 'R-002', 'Kelas 1B', '1', 'Gedung A', 30, 'Kegiatan Belajar Mengajar', 'Guru Kelas 1B', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(3, 'R-003', 'Kelas 2A', '1', 'Gedung A', 30, 'Kegiatan Belajar Mengajar', 'Guru Kelas 2A', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(4, 'R-004', 'Kelas 2B', '1', 'Gedung A', 30, 'Kegiatan Belajar Mengajar', 'Guru Kelas 2B', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(5, 'R-005', 'Ruang Guru', '1', 'Gedung A', 20, 'Ruang Kerja Guru', 'Kepala Sekolah', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(6, 'R-006', 'Ruang Kepala Sekolah', '1', 'Gedung A', 5, 'Ruang Kerja Kepala Sekolah', 'Kepala Sekolah', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(7, 'R-007', 'Lab Komputer', '2', 'Gedung B', 40, 'Praktikum Komputer', 'Guru TIK', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(8, 'R-008', 'Perpustakaan', '1', 'Gedung B', 50, 'Peminjaman Buku', 'Pustakawan', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(9, 'R-009', 'Ruang UKS', '1', 'Gedung A', 10, 'Kesehatan Siswa', 'Guru UKS', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(10, 'R-010', 'Kantin', '1', 'Gedung C', 100, 'Makan Minum Siswa', 'Pengelola Kantin', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(11, 'R-011', 'Musholla', '1', 'Gedung C', 150, 'Ibadah', 'Guru PAI', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48'),
(12, 'R-012', 'Ruang Olahraga', '1', 'Gedung D', 200, 'Aktivitas Olahraga', 'Guru Olahraga', NULL, '2026-04-16 10:05:48', '2026-04-16 10:05:48');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `level` enum('admin','staff') DEFAULT 'staff',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `level`, `created_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', '2026-04-16 09:23:48');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `inventaris`
--
ALTER TABLE `inventaris`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ruangan_id` (`ruangan_id`);

--
-- Indeks untuk tabel `kondisi_aset`
--
ALTER TABLE `kondisi_aset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `inventaris_id` (`inventaris_id`);

--
-- Indeks untuk tabel `ruangan`
--
ALTER TABLE `ruangan`
  ADD PRIMARY KEY (`id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `inventaris`
--
ALTER TABLE `inventaris`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT untuk tabel `kondisi_aset`
--
ALTER TABLE `kondisi_aset`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ruangan`
--
ALTER TABLE `ruangan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `inventaris`
--
ALTER TABLE `inventaris`
  ADD CONSTRAINT `1` FOREIGN KEY (`ruangan_id`) REFERENCES `ruangan` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `kondisi_aset`
--
ALTER TABLE `kondisi_aset`
  ADD CONSTRAINT `1` FOREIGN KEY (`inventaris_id`) REFERENCES `inventaris` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
