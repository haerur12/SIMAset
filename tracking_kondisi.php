<?php
require 'config.php';
if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

// Filter & Search
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$conditions = [];
if ($filter != 'all') $conditions[] = "i.kondisi_aset = '$filter'";
if ($search) $conditions[] = "(i.nama_barang_108 LIKE '%$search%' OR i.spesifikasi_nama_barang LIKE '%$search%')";
$where = $conditions ? "WHERE " . implode(' AND ', $conditions) : "";

$query = "SELECT i.*, r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id $where ORDER BY i.created_at DESC";
$result = mysqli_query($conn, $query);

// Statistik
$total = mysqli_query($conn, "SELECT COUNT(*) as t FROM inventaris")->fetch_assoc()['t'];
$baik = mysqli_query($conn, "SELECT COUNT(*) as t FROM inventaris WHERE kondisi_aset='Baik'")->fetch_assoc()['t'];
$perlu_perhatian = $total - $baik;

// Detail Asset (jika ada ID)
$detail = null;
if(isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $detail = mysqli_query($conn, "SELECT i.*, r.nama_ruangan FROM inventaris i LEFT JOIN ruangan r ON i.ruangan_id = r.id WHERE i.id=$id LIMIT 1")->fetch_assoc();
    $riwayat = mysqli_query($conn, "SELECT * FROM kondisi_aset WHERE inventaris_id=$id ORDER BY created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Modern - Inventaris SDN Curug 01</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0;
            --primary: #0f172a; --accent: #3b82f6;
            --baik: #10b981; --ringan: #f59e0b; --berat: #ef4444; --perbaikan: #6366f1;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03);
            --shadow-hover: 0 20px 25px -5px rgba(0,0,0,0.08), 0 10px 10px -5px rgba(0,0,0,0.02);
            --radius: 16px;
        }
        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }
        body { background: var(--bg); color: #334155; margin: 0; overflow-x: hidden; }
        
        /* Sidebar */
        .sidebar { position: fixed; left: 0; top: 0; width: 260px; height: 100vh; background: var(--primary); color: white; padding: 2rem 1rem; z-index: 100; transition: 0.3s; }
        .sidebar-brand { text-align: center; margin-bottom: 2rem; }
        .sidebar-brand i { font-size: 2.5rem; margin-bottom: 0.5rem; color: var(--accent); }
        .sidebar a { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #cbd5e1; text-decoration: none; border-radius: 12px; margin-bottom: 6px; transition: 0.2s; font-weight: 500; }
        .sidebar a:hover, .sidebar a.active { background: rgba(255,255,255,0.1); color: white; transform: translateX(4px); }
        .sidebar a i { width: 20px; text-align: center; }
        
        /* Main */
        .main { margin-left: 260px; padding: 2rem; min-height: 100vh; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; }
        .header h1 { font-size: 1.8rem; font-weight: 700; margin: 0; }
        .header span { color: #64748b; font-size: 0.9rem; }
        
        /* Filter Chips */
        .chips { display: flex; gap: 8px; flex-wrap: wrap; }
        .chip { padding: 8px 16px; border-radius: 50px; background: white; border: 1px solid var(--border); cursor: pointer; font-size: 0.85rem; font-weight: 500; transition: 0.2s; }
        .chip:hover { border-color: var(--accent); color: var(--accent); }
        .chip.active { background: var(--primary); color: white; border-color: var(--primary); }
        
        /* Stats */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: var(--card); padding: 1.5rem; border-radius: var(--radius); box-shadow: var(--shadow); transition: 0.3s; position: relative; overflow: hidden; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
        .stat-card h3 { font-size: 2rem; font-weight: 700; margin: 0.5rem 0 0; }
        .stat-card i { position: absolute; right: 1.5rem; top: 1.5rem; font-size: 1.5rem; opacity: 0.2; }
        .stat-card.green { border-left: 4px solid var(--baik); }
        .stat-card.orange { border-left: 4px solid var(--ringan); }
        .stat-card.blue { border-left: 4px solid var(--accent); }
        
        /* Search */
        .search-box { position: relative; width: 300px; }
        .search-box input { width: 100%; padding: 10px 16px 10px 40px; border: 1px solid var(--border); border-radius: 12px; font-size: 0.9rem; background: white; transition: 0.2s; }
        .search-box input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(59,130,246,0.15); }
        .search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        
        /* Asset Grid */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .card { background: var(--card); border-radius: var(--radius); padding: 1.5rem; box-shadow: var(--shadow); transition: 0.3s; cursor: pointer; position: relative; overflow: hidden; }
        .card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); }
        .card::after { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; }
        .card.baik::after { background: var(--baik); }
        .card.ringan::after { background: var(--ringan); }
        .card.berat::after { background: var(--berat); }
        .card.perbaikan::after { background: var(--perbaikan); }
        
        .card-head { display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem; }
        .card-title { font-weight: 600; font-size: 1.1rem; margin: 0; line-height: 1.3; }
        .card-sub { color: #64748b; font-size: 0.85rem; margin-top: 4px; }
        
        /* Condition Ring */
        .ring { width: 48px; height: 48px; border-radius: 50%; position: relative; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; }
        .ring::before { content: ''; position: absolute; inset: 0; border-radius: 50%; background: conic-gradient(var(--clr) var(--deg), #e2e8f0 0); }
        .ring span { position: relative; z-index: 1; background: white; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .ring.baik { --clr: var(--baik); --deg: 100%; }
        .ring.ringan { --clr: var(--ringan); --deg: 75%; }
        .ring.berat { --clr: var(--berat); --deg: 40%; }
        .ring.perbaikan { --clr: var(--perbaikan); --deg: 60%; }
        
        .card-meta { display: flex; justify-content: space-between; align-items: center; margin-top: 1.2rem; padding-top: 1rem; border-top: 1px solid var(--border); font-size: 0.85rem; color: #64748b; }
        .btn-view { background: var(--primary); color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: 0.2s; }
        .btn-view:hover { background: var(--accent); }
        
        /* Modal */
        .modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,0.6); backdrop-filter: blur(4px); z-index: 1000; display: none; align-items: center; justify-content: center; opacity: 0; transition: 0.3s; }
        .modal-overlay.open { display: flex; opacity: 1; }
        .modal-box { background: white; width: 90%; max-width: 700px; max-height: 90vh; border-radius: 20px; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: scale(0.95); transition: 0.3s; }
        .modal-overlay.open .modal-box { transform: scale(1); }
        .modal-head { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 1.5rem; overflow-y: auto; max-height: 70vh; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #64748b; transition: 0.2s; }
        .close-btn:hover { color: var(--berat); transform: rotate(90deg); }
        
        .timeline { position: relative; padding-left: 24px; margin-top: 1rem; }
        .timeline::before { content: ''; position: absolute; left: 8px; top: 8px; bottom: 8px; width: 2px; background: #e2e8f0; }
        .tl-item { position: relative; margin-bottom: 1.5rem; padding: 12px 16px; background: #f8fafc; border-radius: 12px; border-left: 3px solid var(--accent); }
        .tl-item::before { content: ''; position: absolute; left: -22px; top: 16px; width: 10px; height: 10px; border-radius: 50%; background: var(--accent); border: 2px solid white; box-shadow: 0 0 0 2px var(--accent); }
        .tl-date { font-size: 0.75rem; color: #64748b; margin-bottom: 4px; }
        .tl-status { font-weight: 600; font-size: 0.9rem; }
        .tl-note { margin-top: 6px; font-size: 0.85rem; color: #475569; }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main { margin-left: 0; padding: 1.5rem; }
            .grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
            .search-box { width: 100%; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-school"></i>
        <h4 style="margin:0">SIMAset</h4>
        <small>SDN Curug 01</small>
    </div>
    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="ruangan.php"><i class="fas fa-door-open"></i> Ruangan</a>
    <a href="tambah.php"><i class="fas fa-plus-circle"></i> Tambah Aset</a>
    <a href="kondisi_aset.php"><i class="fas fa-tools"></i> Kondisi Aset</a>
    <a href="tracking_kondisi.php" class="active"><i class="fas fa-chart-line"></i> Tracking</a>
    <a href="export_excel.php"><i class="fas fa-file-excel"></i> Export</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main -->
<div class="main">
    <div class="header">
        <div>
            <h1>Tracking Kondisi</h1>
            <span>Pantau perubahan kondisi aset secara real-time</span>
        </div>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="searchInput" placeholder="Cari nama barang..." value="<?= $search ?>">
        </div>
    </div>

    <!-- Filter Chips -->
    <div class="chips mb-4">
        <div class="chip <?= $filter=='all'?'active':'' ?>" onclick="location.href='?filter=all'">Semua</div>
        <div class="chip <?= $filter=='Baik'?'active':'' ?>" onclick="location.href='?filter=Baik'">✅ Baik</div>
        <div class="chip <?= $filter=='Rusak Ringan'?'active':'' ?>" onclick="location.href='?filter=Rusak Ringan'">️ Rusak Ringan</div>
        <div class="chip <?= $filter=='Rusak Berat'?'active':'' ?>" onclick="location.href='?filter=Rusak Berat'">🔴 Rusak Berat</div>
        <div class="chip <?= $filter=='Dalam Perbaikan'?'active':'' ?>" onclick="location.href='?filter=Dalam Perbaikan'">🔧 Dalam Perbaikan</div>
    </div>

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card blue">
            <i class="fas fa-cubes"></i>
            <span style="color:#64748b; font-size:0.85rem; font-weight:500;">Total Aset</span>
            <h3><?= $total ?></h3>
        </div>
        <div class="stat-card green">
            <i class="fas fa-check-circle"></i>
            <span style="color:#64748b; font-size:0.85rem; font-weight:500;">Kondisi Baik</span>
            <h3><?= $baik ?></h3>
        </div>
        <div class="stat-card orange">
            <i class="fas fa-exclamation-triangle"></i>
            <span style="color:#64748b; font-size:0.85rem; font-weight:500;">Perlu Perhatian</span>
            <h3><?= $perlu_perhatian ?></h3>
        </div>
    </div>

    <!-- Grid -->
    <div class="grid">
        <?php while($row = mysqli_fetch_assoc($result)): 
            $cls = match($row['kondisi_aset']) {
                'Baik' => 'baik',
                'Rusak Ringan' => 'ringan',
                'Rusak Berat' => 'berat',
                default => 'perbaikan'
            };
            $deg = match($row['kondisi_aset']) {
                'Baik' => '100%',
                'Rusak Ringan' => '75%',
                'Rusak Berat' => '40%',
                default => '60%'
            };
        ?>
        <div class="card <?= $cls ?>" onclick="openModal(<?= $row['id'] ?>)">
            <div class="card-head">
                <div>
                    <h3 class="card-title"><?= $row['nama_barang_108'] ?></h3>
                    <div class="card-sub"><i class="fas fa-map-marker-alt"></i> <?= $row['nama_ruangan'] ?? 'Belum ditentukan' ?></div>
                </div>
                <div class="ring <?= $cls ?>" style="--deg: <?= $deg ?>">
                    <span><?= substr($row['kondisi_aset'], 0, 1) ?></span>
                </div>
            </div>
            <div class="card-meta">
                <span><i class="far fa-clock"></i> Update: <?= date('d M Y', strtotime($row['created_at'])) ?></span>
                <button class="btn-view">Detail <i class="fas fa-arrow-right" style="margin-left:4px"></i></button>
            </div>
        </div>
        <?php endwhile; ?>
        <?php if(mysqli_num_rows($result) == 0): ?>
            <div style="grid-column:1/-1; text-align:center; padding:3rem; color:#94a3b8;">
                <i class="fas fa-search-minus fa-3x mb-3"></i>
                <p>Tidak ada aset ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal-overlay" id="modal">
    <div class="modal-box">
        <div class="modal-head">
            <h4 style="margin:0"><i class="fas fa-chart-line" style="color:var(--accent); margin-right:8px;"></i> Riwayat Kondisi</h4>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content injected via JS/AJAX or PHP pre-load -->
            <?php if($detail): ?>
                <div style="display:flex; gap:1.5rem; margin-bottom:1.5rem; flex-wrap:wrap;">
                    <div style="flex:1; min-width:200px;">
                        <h3 style="margin:0 0 4px;"><?= $detail['nama_barang_108'] ?></h3>
                        <span style="color:#64748b; font-size:0.9rem;"><?= $detail['spesifikasi_nama_barang'] ?></span>
                    </div>
                    <div style="text-align:right;">
                        <div class="ring <?= match($detail['kondisi_aset']){'Baik'=>'baik','Rusak Ringan'=>'ringan','Rusak Berat'=>'berat',default=>'perbaikan'} ?>" style="--deg: <?= match($detail['kondisi_aset']){'Baik'=>'100%','Rusak Ringan'=>'75%','Rusak Berat'=>'40%',default=>'60%'} ?>; width:60px; height:60px; font-size:1.2rem;">
                            <span><?= substr($detail['kondisi_aset'],0,1) ?></span>
                        </div>
                        <div style="margin-top:6px; font-weight:600;"><?= $detail['kondisi_aset'] ?></div>
                    </div>
                </div>
                <div class="timeline">
                    <?php while($h = mysqli_fetch_assoc($riwayat)): ?>
                    <div class="tl-item">
                        <div class="tl-date"><?= date('d F Y, H:i', strtotime($h['created_at'])) ?> oleh <?= $h['petugas'] ?></div>
                        <div class="tl-status" style="color: <?= match($h['kondisi']){'Baik'=>'var(--baik)','Rusak Ringan'=>'var(--ringan)','Rusak Berat'=>'var(--berat)',default=>'var(--perbaikan)'} ?>">
                            <?= $h['kondisi'] ?>
                        </div>
                        <?php if($h['keterangan']): ?>
                        <div class="tl-note"><?= nl2br($h['keterangan']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align:center; padding:2rem; color:#94a3b8;">
                    <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                    <p>Memuat data...</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Search debounce
    let timeout;
    document.getElementById('searchInput').addEventListener('input', function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            location.href = '?search=' + encodeURIComponent(this.value) + '&filter=<?= $filter ?>';
        }, 500);
    });

    // Modal
    function openModal(id) {
        // For simplicity, we use PHP pre-load. In production, use fetch() for AJAX.
        document.getElementById('modal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('modal').classList.remove('open');
        document.body.style.overflow = '';
    }
    document.getElementById('modal').addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
    document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });
</script>

</body>
</html>