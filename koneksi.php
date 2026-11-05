<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

// Sejak PHP 8.1 mysqli melempar exception secara default.
// Dimatikan supaya perilakunya sama seperti mysql_* lama (gagal = return false).
mysqli_report(MYSQLI_REPORT_OFF);

$server   = "192.168.0.237";
$username = "umum";
$password = "RSmndb2020";
$database = "rs_pendidikan";


// $server   = "localhost";
// $username = "root";
// $password = "";
// $database = "rs_pendidikan";


// Pesan error sengaja dibuat umum karena halaman ini diakses dari internet:
// detail error (host, user, dll) tidak boleh bocor ke pengunjung.
// Detailnya dicatat di error log server.
$con = @mysqli_connect($server, $username, $password);
if (!$con) {
    error_log("daftar_mhs: koneksi gagal : " . mysqli_connect_error());
    die("Layanan sedang tidak tersedia, silakan coba beberapa saat lagi.");
}
if (!@mysqli_select_db($con, $database)) {
    error_log("daftar_mhs: database tidak bisa dibuka : " . mysqli_error($con));
    die("Layanan sedang tidak tersedia, silakan coba beberapa saat lagi.");
}
?>