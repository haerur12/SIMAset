<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="col-md-2 sidebar">

<div class="sidebar-brand">

<img src="assets/img/logo.png" class="sidebar-logo">

<h4>Inventaris Sekolah</h4>
<h6>SDN Curug 01</h6>

</div>

<div class="sidebar-menu">

<a href="dashboard.php"
class="<?= ($current_page=='dashboard.php')?'active':'' ?>">

<i class="fas fa-home"></i>
Dashboard

</a>

<a href="ruangan.php"
class="<?= ($current_page=='ruangan.php')?'active':'' ?>">

<i class="fas fa-door-open"></i>
Manajemen Ruangan

</a>

<a href="kategori_aset.php"
class="<?= ($current_page=='kategori_aset.php')?'active':'' ?>">

<i class="fas fa-tags"></i>
Kategori Aset

</a>

<a href="tambah.php"
class="<?= ($current_page=='tambah.php')?'active':'' ?>">

<i class="fas fa-plus-circle"></i>
Tambah Aset

</a>

<a href="kondisi_aset.php"
class="<?= ($current_page=='kondisi_aset.php')?'active':'' ?>">

<i class="fas fa-tools"></i>
Kondisi Aset

</a>

<a href="export_excel.php"
class="<?= ($current_page=='export_excel.php')?'active':'' ?>">

<i class="fas fa-file-excel"></i>
Export Excel

</a>

<a href="logout.php">

<i class="fas fa-sign-out-alt"></i>
Logout

</a>

</div>

</div>