<?php
// generate_password.php - HAPUS FILE INI SETELAH DIGUNAKAN
$password = 'kepsek123'; // Ganti dengan password yang diinginkan
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Password: $password<br>";
echo "Hash: $hash<br><br>";
echo "SQL Query:<br>";
echo "INSERT INTO users (username, password, nama_lengkap, level) VALUES ('kepsek', '$hash', 'Kepala Sekolah SDN Curug 01', 'kepsek');";
?>