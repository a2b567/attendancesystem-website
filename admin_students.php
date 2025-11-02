<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

// Fetch students
$students = $conn->query("SELECT * FROM students ORDER BY firstname ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<link rel="stylesheet" href="assets/styles.css">
<style>
/* General body */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #f5f6fa;
    margin: 0;
    padding: 0;
}

/* Navigation */
nav {
    display: flex;
    justify-content: flex-end;
    background-color: #2f3640;
    padding: 15px 30px;
}

nav a {
    color: #f5f6fa;
    text-decoration: none;
    margin-left: 20px;
    font-weight: bold;
    transition: 0.3s;
}

nav a:hover {
    color: #00a8ff;
}

/* Container */
.container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Header */
h2 {
    color: #2f3640;
    margin-bottom: 20px;
}

/* Grid layout */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
}

/* Card styling */
.student-card {
    background-color: #fff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.06);
    text-align: center;
    transition: 0.3s;
}

.student-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* Student info */
.student-card h3 {
    margin: 10px 0 5px 0;
    font-size: 18px;
    color: #2f3640;
}

.student-card p {
    margin: 5px 0;
    color: #718093;
    font-size: 14px;
}

/* QR image */
.qr-img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 1px solid #dcdde1;
    margin-bottom: 10px;
}
</style>
</head>
<body>
<nav>
  <a href="add_student.php">➕ Add Student</a>
  <a href="mark_attendance.php">📋 Mark Attendance</a>
  <a href="logout.php">🚪 Logout</a>
</nav>

<div class="container">
    <h2>Student Dashboard</h2>
    <div class="cards-grid">
        <?php while($s = $students->fetch_assoc()): ?>
        <div class="student-card">
            <?php
            $qrFile = 'qrcodes/' . $s['student_number'] . '.png';
            if(file_exists($qrFile)) {
                echo '<img src="' . $qrFile . '" class="qr-img" alt="QR Code">';
            } else {
                echo '<div class="qr-img" style="display:flex;align-items:center;justify-content:center;color:#bbb;">No QR</div>';
            }
            ?>
            <h3><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></h3>
            <p>Email: <?= htmlspecialchars($s['email'] ?? 'N/A') ?></p>
            <p>Student No: <?= htmlspecialchars($s['student_number']) ?></p>
        </div>
        <?php endwhile; ?>
    </div>
</div>
</body>
</html>
