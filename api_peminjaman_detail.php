<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    echo "Unauthorized";
    exit;
}

$id = intval($_GET['id'] ?? 0);
$data = mysqli_query($conn, "SELECT p.*, i.nama_barang_108, i.spesifikasi_nama_barang, i.jumlah as stok_total, i.satuan,
    r.nama_ruangan, u.nama_lengkap as petugas_input
    FROM peminjaman_aset p
    LEFT JOIN inventaris i ON p.inventaris_id = i.id
    LEFT JOIN ruangan r ON i.ruangan_id = r.id
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = $id")->fetch_assoc();

if(!$data) {
    echo "<p class='text-center text-gray-500'>Data tidak ditemukan</p>";
    exit;
}

$is_terlambat = ($data['status'] === 'dipinjam' && $data['tanggal_kembali_rencana'] < date('Y-m-d'));
$status_label = [
    'dipinjam' => ['bg-blue-100 text-blue-700', 'fa-clock', 'Sedang Dipinjam'],
    'terlambat' => ['bg-red-100 text-red-700', 'fa-exclamation-triangle', 'Terlambat'],
    'dikembalikan' => ['bg-emerald-100 text-emerald-700', 'fa-check-circle', 'Sudah Dikembalikan'],
];
$status = $status_label[$is_terlambat ? 'terlambat' : $data['status']];

function formatTgl($tgl) {
    return $tgl ? date('d F Y', strtotime($tgl)) : '-';
}

// ✅ Hitung breakdown
$has_breakdown_pinjam = ($data['kondisi_baik_pinjam'] + $data['kondisi_rusak_ringan_pinjam'] + $data['kondisi_rusak_berat_pinjam'] + $data['kondisi_perbaikan_pinjam']) > 0;
$has_breakdown_kembali = ($data['kondisi_baik_kembali'] + $data['kondisi_rusak_ringan_kembali'] + $data['kondisi_rusak_berat_kembali'] + $data['kondisi_perbaikan_kembali']) > 0;
?>

<div class="space-y-4">
    <!-- Status Header -->
    <div class="flex items-center justify-between p-4 rounded-lg <?= $status[0] ?>">
        <div class="flex items-center gap-3">
            <i class="fas <?= $status[1] ?> text-2xl"></i>
            <div>
                <p class="text-xs uppercase tracking-wider opacity-70">Status</p>
                <p class="font-bold text-lg"><?= $status[2] ?></p>
            </div>
        </div>
        <?php if($is_terlambat): 
            $hari = floor((time() - strtotime($data['tanggal_kembali_rencana'])) / 86400);
        ?>
        <div class="text-right">
            <p class="text-xs opacity-70">Keterlambatan</p>
            <p class="font-bold text-lg"><?= $hari ?> hari</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Info Aset -->
    <div class="bg-gradient-to-br from-primary/5 to-primary/10 dark:from-primary/20 dark:to-primary/10 rounded-lg p-4 border border-primary/20">
        <p class="text-xs font-bold text-primary dark:text-primary-light mb-2 flex items-center gap-1">
            <i class="fas fa-box"></i> Informasi Aset
        </p>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Nama Barang</span><span class="font-semibold"><?= htmlspecialchars($data['nama_barang_108']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Spesifikasi</span><span class="text-right max-w-[200px] text-xs"><?= htmlspecialchars($data['spesifikasi_nama_barang']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Ruangan</span><span class="font-semibold"><?= htmlspecialchars($data['nama_ruangan'] ?? '-') ?></span></div>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Jumlah Dipinjam</span><span class="font-bold text-primary"><?= $data['jumlah'] ?> <?= $data['satuan'] ?></span></div>
        </div>
    </div>
    
    <!-- Info Peminjam -->
    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 border border-blue-200 dark:border-blue-800">
        <p class="text-xs font-bold text-blue-700 dark:text-blue-300 mb-2 flex items-center gap-1">
            <i class="fas fa-user-tie"></i> Data Peminjam
        </p>
        <div class="space-y-1 text-sm">
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Nama</span><span class="font-semibold"><?= htmlspecialchars($data['peminjam']) ?></span></div>
            <?php if($data['nip_peminjam']): ?>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">NIP</span><span class="font-mono"><?= htmlspecialchars($data['nip_peminjam']) ?></span></div>
            <?php endif; ?>
            <?php if($data['unit_kerja']): ?>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Unit Kerja</span><span><?= htmlspecialchars($data['unit_kerja']) ?></span></div>
            <?php endif; ?>
            <?php if($data['no_hp']): ?>
            <div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">No. HP</span><span><?= htmlspecialchars($data['no_hp']) ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Periode -->
    <div class="bg-amber-50 dark:bg-amber-900/20 rounded-lg p-4 border border-amber-200 dark:border-amber-800">
        <p class="text-xs font-bold text-amber-700 dark:text-amber-300 mb-2 flex items-center gap-1">
            <i class="fas fa-calendar"></i> Periode Peminjaman
        </p>
        <div class="grid grid-cols-2 gap-3 text-sm">
            <div>
                <p class="text-xs text-gray-600 dark:text-gray-400">Tanggal Pinjam</p>
                <p class="font-semibold"><?= formatTgl($data['tanggal_pinjam']) ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-600 dark:text-gray-400">Jatuh Tempo</p>
                <p class="font-semibold text-red-600"><?= formatTgl($data['tanggal_kembali_rencana']) ?></p>
            </div>
            <?php if($data['tanggal_kembali_aktual']): ?>
            <div class="col-span-2">
                <p class="text-xs text-gray-600 dark:text-gray-400">Dikembalikan Pada</p>
                <p class="font-semibold text-emerald-600"><?= formatTgl($data['tanggal_kembali_aktual']) ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ✅ BREAKDOWN KONDISI (BARU!) -->
    <div class="bg-gradient-to-br from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-lg p-4 border border-purple-200 dark:border-purple-800">
        <p class="text-xs font-bold text-purple-700 dark:text-purple-300 mb-3 flex items-center gap-1">
            <i class="fas fa-clipboard-list"></i> Breakdown Kondisi
        </p>
        
        <!-- Kondisi Saat Dipinjam -->
        <div class="mb-3">
            <p class="text-[10px] text-gray-600 dark:text-gray-400 font-semibold mb-1">Saat Dipinjam:</p>
            <?php if($has_breakdown_pinjam): ?>
            <div class="grid grid-cols-4 gap-1">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded text-center">
                    <p class="text-[9px] text-emerald-700 dark:text-emerald-300">Baik</p>
                    <p class="font-bold text-emerald-700 dark:text-emerald-300"><?= $data['kondisi_baik_pinjam'] ?></p>
                </div>
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded text-center">
                    <p class="text-[9px] text-amber-700 dark:text-amber-300">R.Ringan</p>
                    <p class="font-bold text-amber-700 dark:text-amber-300"><?= $data['kondisi_rusak_ringan_pinjam'] ?></p>
                </div>
                <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded text-center">
                    <p class="text-[9px] text-red-700 dark:text-red-300">R.Berat</p>
                    <p class="font-bold text-red-700 dark:text-red-300"><?= $data['kondisi_rusak_berat_pinjam'] ?></p>
                </div>
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded text-center">
                    <p class="text-[9px] text-blue-700 dark:text-blue-300">Perbaikan</p>
                    <p class="font-bold text-blue-700 dark:text-blue-300"><?= $data['kondisi_perbaikan_pinjam'] ?></p>
                </div>
            </div>
            <?php else: ?>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                <i class="fas fa-check-circle text-emerald-500 mr-1"></i>
                <?= $data['kondisi_sebelum'] ?: 'Baik' ?> (semua unit)
            </p>
            <?php endif; ?>
        </div>
        
        <!-- Kondisi Saat Dikembalikan -->
        <?php if($has_breakdown_kembali): ?>
        <div class="pt-3 border-t border-purple-200 dark:border-purple-800">
            <p class="text-[10px] text-gray-600 dark:text-gray-400 font-semibold mb-1">Saat Dikembalikan:</p>
            <div class="grid grid-cols-4 gap-1">
                <div class="p-2 bg-emerald-100 dark:bg-emerald-900/30 rounded text-center">
                    <p class="text-[9px] text-emerald-700 dark:text-emerald-300">Baik</p>
                    <p class="font-bold text-emerald-700 dark:text-emerald-300"><?= $data['kondisi_baik_kembali'] ?></p>
                </div>
                <div class="p-2 bg-amber-100 dark:bg-amber-900/30 rounded text-center">
                    <p class="text-[9px] text-amber-700 dark:text-amber-300">R.Ringan</p>
                    <p class="font-bold text-amber-700 dark:text-amber-300"><?= $data['kondisi_rusak_ringan_kembali'] ?></p>
                </div>
                <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded text-center">
                    <p class="text-[9px] text-red-700 dark:text-red-300">R.Berat</p>
                    <p class="font-bold text-red-700 dark:text-red-300"><?= $data['kondisi_rusak_berat_kembali'] ?></p>
                </div>
                <div class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded text-center">
                    <p class="text-[9px] text-blue-700 dark:text-blue-300">Perbaikan</p>
                    <p class="font-bold text-blue-700 dark:text-blue-300"><?= $data['kondisi_perbaikan_kembali'] ?></p>
                </div>
            </div>
            
            <!-- Perbandingan -->
            <?php 
            $total_rusak_pinjam = $data['kondisi_rusak_ringan_pinjam'] + $data['kondisi_rusak_berat_pinjam'] + $data['kondisi_perbaikan_pinjam'];
            $total_rusak_kembali = $data['kondisi_rusak_ringan_kembali'] + $data['kondisi_rusak_berat_kembali'] + $data['kondisi_perbaikan_kembali'];
            if($total_rusak_kembali > $total_rusak_pinjam): 
            ?>
            <div class="mt-2 p-2 bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 rounded text-xs text-red-700 dark:text-red-300">
                <i class="fas fa-exclamation-triangle mr-1"></i>
                <strong>Terjadi Kerusakan:</strong> <?= ($total_rusak_kembali - $total_rusak_pinjam) ?> unit rusak saat dipinjam
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if($data['keperluan']): ?>
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><i class="fas fa-bullseye"></i> Keperluan</p>
        <p class="text-sm"><?= htmlspecialchars($data['keperluan']) ?></p>
    </div>
    <?php endif; ?>
    
    <?php if($data['catatan_pengembalian']): ?>
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3">
        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1"><i class="fas fa-sticky-note"></i> Catatan Pengembalian</p>
        <p class="text-sm"><?= nl2br(htmlspecialchars($data['catatan_pengembalian'])) ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Meta -->
    <div class="text-xs text-gray-500 dark:text-gray-400 pt-2 border-t border-gray-200 dark:border-gray-700">
        <p><i class="fas fa-user mr-1"></i> Dicatat oleh: <?= htmlspecialchars($data['petugas_input'] ?? '-') ?></p>
        <p><i class="fas fa-clock mr-1"></i> <?= date('d/m/Y H:i', strtotime($data['created_at'])) ?></p>
        <?php if($data['petugas_serah_terima']): ?>
        <p><i class="fas fa-handshake mr-1"></i> Diterima oleh: <?= htmlspecialchars($data['petugas_serah_terima']) ?></p>
        <?php endif; ?>
    </div>
</div>