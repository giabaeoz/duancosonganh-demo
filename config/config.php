<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

date_default_timezone_set('Asia/Ho_Chi_Minh');
define('BASE_URL', '/demo');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = new mysqli('localhost', 'root', '', 'qlts');
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '+07:00'");
