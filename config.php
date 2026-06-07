<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Jangan tampilkan error ke user
require_once __DIR__ . '/auth.php';

$host = "127.0.0.1";
$user = "root";
$pass = "";
$db   = "db_inventaris_sekolah";

$conn = mysqli_connect("127.0.0.1", "root", "", "db_inventaris_sekolah", 3306);

if (!$conn) {
    error_log("Database Connection Error: " . mysqli_connect_error(), 3, "logs/error.log");
    die("Koneksi database gagal. Hubungi administrator.");
}

// Set charset UTF-8
mysqli_set_charset($conn, "utf8mb4");

// ✅ Include auth helper
require_once __DIR__ . '/auth.php';

// Fungsi untuk format rupiah
function formatRupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk format tanggal
function formatTanggal($tanggal) {
    if(empty($tanggal)) return "-";
    $bulan = array(
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    );
    $pecahkan = explode('-', $tanggal);
    return $pecahkan[2] . ' ' . $bulan[(int)$pecahkan[1]] . ' ' . $pecahkan[0];
}

// ============================================
// ✅ HELPER: Pengaturan Sistem
// ============================================

/**
 * Ambil nilai setting dari database
 */
function get_setting($key_name, $default = null) {
    global $conn;
    $result = mysqli_query($conn, "SELECT value FROM pengaturan_sistem WHERE key_name = '$key_name' LIMIT 1");
    if($result && mysqli_num_rows($result) > 0) {
        return mysqli_fetch_assoc($result)['value'];
    }
    return $default;
}

/**
 * Simpan/Update setting
 */
function set_setting($key_name, $value, $keterangan = null) {
    global $conn;
    $value_esc = mysqli_real_escape_string($conn, $value);
    $ket_esc = $keterangan ? "'" . mysqli_real_escape_string($conn, $keterangan) . "'" : "NULL";
    
    return mysqli_query($conn, "INSERT INTO pengaturan_sistem (key_name, value, keterangan) 
        VALUES ('$key_name', '$value_esc', $ket_esc)
        ON DUPLICATE KEY UPDATE value = '$value_esc', keterangan = $ket_esc");
}

/**
 * Cek apakah sudah waktunya buat arsip bulan lalu
 * Return: array ['show' => bool, 'bulan' => int, 'tahun' => int, 'status' => string]
 */
function cek_waktu_arsip() {
    global $conn;
    if(!$conn) return ['show' => false, 'error' => 'No database connection'];

    // Ambil setting
    $setting_raw = get_setting('tanggal_arsip_bulanan', null);
    $setting = $setting_raw ? json_decode($setting_raw, true) : null;

    // Jika setting lama (belum ada JSON), parse manual
    if(!$setting && $setting_raw) {
        $setting = [
            'tanggal' => intval($setting_raw),
            'bulan' => date('n'),
            'tahun' => date('Y')
        ];
    }

    // Default jika belum ada setting
    if(!$setting) {
        $setting = [
            'tanggal' => 27,
            'bulan' => date('n'),
            'tahun' => date('Y')
        ];
    }

    $tanggal_setting = intval($setting['tanggal'] ?? 27);
    $bulan_setting = intval($setting['bulan'] ?? date('n'));
    $tahun_setting = intval($setting['tahun'] ?? date('Y'));

    // Hitung bulan yang perlu diarsipkan (bulan sebelumnya dari setting)
    $hari_ini = intval(date('j'));
    $bulan_sekarang = intval(date('n'));
    $tahun_sekarang = intval(date('Y'));

    // Tentukan bulan target berdasarkan setting
    if($bulan_sekarang > $bulan_setting || ($bulan_sekarang == $bulan_setting && $hari_ini >= $tanggal_setting)) {
        $bulan_target = $bulan_setting;
        $tahun_target = $tahun_sekarang;
    } else {
        if($bulan_setting == 1) {
            $bulan_target = 12;
            $tahun_target = $tahun_sekarang - 1;
        } else {
            $bulan_target = $bulan_setting - 1;
            $tahun_target = $tahun_sekarang;
        }
    }

    // Cek apakah sudah ada arsip untuk bulan target
    $arsip_exists = @mysqli_query($conn, "SELECT id FROM peminjaman_arsip_bulanan 
        WHERE bulan = $bulan_target AND tahun = $tahun_target LIMIT 1");

    if($arsip_exists && @mysqli_num_rows($arsip_exists) > 0) {
        return ['show' => false, 'reason' => 'already_archived'];
    }

    // Cek apakah ada data peminjaman
    $data_exists = @mysqli_query($conn, "SELECT COUNT(*) as total FROM peminjaman_aset 
        WHERE MONTH(tanggal_pinjam) = $bulan_target AND YEAR(tanggal_pinjam) = $tahun_target");
    $data_exists = $data_exists ? mysqli_fetch_assoc($data_exists)['total'] : 0;

    if($data_exists == 0) {
        return ['show' => false, 'reason' => 'no_data'];
    }

    // Cek status notifikasi
    $notif_log = @mysqli_query($conn, "SELECT status FROM arsip_notifikasi_log 
        WHERE bulan = $bulan_target AND tahun = $tahun_target LIMIT 1");

    $status = 'pending';
    if($notif_log && @mysqli_num_rows($notif_log) > 0) {
        $status = mysqli_fetch_assoc($notif_log)['status'];
        if($status === 'completed') {
            return ['show' => false, 'reason' => 'already_completed'];
        }
    } else {
        $user_id = $_SESSION['user_id'] ?? null;
        @mysqli_query($conn, "INSERT INTO arsip_notifikasi_log (bulan, tahun, status, dibuat_oleh) 
            VALUES ($bulan_target, $tahun_target, 'pending', $user_id)");
    }

    if($bulan_sekarang == $bulan_setting && $hari_ini >= $tanggal_setting) {
        return [
            'show' => true,
            'bulan' => $bulan_target,
            'tahun' => $tahun_target,
            'status' => $status,
            'tanggal_setting' => $tanggal_setting,
            'bulan_setting' => $bulan_setting,
            'tahun_setting' => $tahun_setting,
            'jumlah_data' => $data_exists
        ];
    }

    return ['show' => false, 'reason' => 'not_yet'];
}

/**
 * Update status notifikasi arsip
 */
function update_status_notifikasi_arsip($bulan, $tahun, $status) {
    global $conn;
    $bulan = intval($bulan);
    $tahun = intval($tahun);

    if($status === 'completed') {
        return mysqli_query($conn, "INSERT INTO arsip_notifikasi_log (bulan, tahun, status, completed_at) 
            VALUES ($bulan, $tahun, 'completed', NOW())
            ON DUPLICATE KEY UPDATE status = 'completed', completed_at = NOW()");
    } elseif($status === 'dismissed') {
        return mysqli_query($conn, "INSERT INTO arsip_notifikasi_log (bulan, tahun, status, dismissed_at) 
            VALUES ($bulan, $tahun, 'dismissed', NOW())
            ON DUPLICATE KEY UPDATE status = 'dismissed', dismissed_at = NOW()");
    }
    return false;
}

/**
 * Format nama bulan Indonesia
 */
function nama_bulan_indo($bulan) {
    $bulan_list = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];
    return $bulan_list[$bulan] ?? '-';
}