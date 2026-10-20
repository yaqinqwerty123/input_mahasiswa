<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

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
$con = @mysql_connect($server, $username, $password);
if (!$con) {
    error_log("daftar_mhs: koneksi gagal : " . mysql_error());
    die("Layanan sedang tidak tersedia, silakan coba beberapa saat lagi.");
}
if (!@mysql_select_db($database, $con)) {
    error_log("daftar_mhs: database tidak bisa dibuka : " . mysql_error());
    die("Layanan sedang tidak tersedia, silakan coba beberapa saat lagi.");
}
?>
