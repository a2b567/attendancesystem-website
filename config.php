<?php
$host = "localhost";
$user = "root";
$password = "";
$dbname = "attendance";

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_errno) {
    die("❌ Unable to connect to database: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

define('BASE_URL', 'http://localhost/attendance/');
?>
