<?php
require 'config.php';

if(!isset($_SESSION['login'])) {
    header("Location: index.php");
    exit;
}

$id = $_GET['id'];
mysqli_query($conn, "DELETE FROM inventaris WHERE id = $id");

header("Location: dashboard.php");
exit;
?>