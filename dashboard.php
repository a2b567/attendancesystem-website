<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

// Fetch students
$students = $conn->query("SELECT * FROM students ORDER BY firstname ASC");

// Handle POST from QR scan or button
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_number = trim($_POST['student_number'] ?? '');
    $status = $_POST['status'] ?? 'Present'; // default to Present if not set

    if ($student_number !== '' && in_array($status, ['Present', 'Absent'])) {
        $date = date('Y-m-d');

        // Check if attendance for today exists
        $check = $conn->prepare("SELECT id FROM attendance WHERE student_number=? AND date=?");
        $check->bind_param("ss", $student_number, $date);
        $check->execute();
        $check->store_result();

        if ($check->num_rows == 0) {
            // Insert new record
            $insert = $conn->prepare("INSERT INTO attendance (student_number, date, status) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $student_number, $date, $status);
            $insert->execute();
            $insert->close();
        } else {
            // Update existing record
            $update = $conn->prepare("UPDATE attendance SET status=? WHERE student_number=? AND date=?");
            $update->bind_param("sss", $status, $student_number, $date);
            $update->execute();
            $update->close();
        }
        $check->close();
        
        // Redirect to avoid form resubmission
        header("Location: dashboard.php");
        exit();
    }
}

// Fetch today's attendance with status
$today = date('Y-m-d');
$attendance_result = $conn->query("SELECT student_number, status FROM attendance WHERE date='$today'");
$attendance_status = [];
while ($row = $attendance_result->fetch_assoc()) {
    $attendance_status[$row['student_number']] = $row['status'];
}

// Get attendance stats
$total_students = $students->num_rows;
$present_today = 0;
$absent_today = 0;

foreach ($attendance_status as $status) {
    if ($status === 'Present') {
        $present_today++;
    } elseif ($status === 'Absent') {
        $absent_today++;
    }
}

$not_marked = $total_students - ($present_today + $absent_today);
$attendance_percentage = $total_students > 0 ? round(($present_today / $total_students) * 100, 1) : 0;

// Function to get student photo
function getStudentPhoto($student_number) {
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        return null;
    }
    
    $photoPattern = $uploadDir . $student_number . '_*.*';
    $studentPhotos = glob($photoPattern);
    
    if (!empty($studentPhotos) && file_exists($studentPhotos[0])) {
        $photoPath = str_replace(__DIR__ . '/', '', $studentPhotos[0]);
        return $photoPath;
    }
    return null;
}

// Function to get QR code
function getQRCode($student_number) {
    $qrFile = __DIR__ . '/qrcodes/' . $student_number . '.png';
    if (file_exists($qrFile)) {
        return 'qrcodes/' . $student_number . '.png';
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Attendance Dashboard</title>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap Icons -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
<!-- AOS Animation Library -->
<link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
<style>
:root {
    --primary: #4361ee;
    --secondary: #3f37c9;
    --success: #4cc9f0;
    --info: #4895ef;
    --warning: #f72585;
    --light: #f8f9fa;
    --dark: #212529;
    --sidebar-width: 280px;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #333;
    overflow-x: hidden;
    min-height: 100vh;
}

/* Background Animation */
.background-animation {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: -2;
    background: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
    background-size: 400% 400%;
    animation: gradient 15s ease infinite;
}

@keyframes gradient {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Glass Sidebar */
.sidebar {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    border-right: 1px solid rgba(255, 255, 255, 0.2);
    height: 100vh;
    position: fixed;
    z-index: 100;
    overflow-y: auto;
    width: var(--sidebar-width);
    box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
}

.sidebar-content {
    padding: 25px 20px;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
}

.sidebar-brand i {
    font-size: 28px;
    margin-right: 12px;
    color: #fff;
}

.sidebar-brand h2 {
    color: #fff;
    font-weight: 700;
    font-size: 22px;
    margin: 0;
}

.sidebar-nav {
    list-style: none;
    padding: 0;
}

.sidebar-nav li {
    margin-bottom: 8px;
}

.sidebar-nav a {
    display: flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 10px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.sidebar-nav a:hover,
.sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.2);
    color: #fff;
    transform: translateX(5px);
}

.sidebar-nav i {
    margin-right: 12px;
    font-size: 18px;
    width: 24px;
    text-align: center;
}

/* Main Content */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 25px;
    min-height: 100vh;
}

/* Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.dashboard-header h1 {
    color: #fff;
    font-weight: 700;
    font-size: 32px;
    margin-bottom: 0;
    text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.header-actions {
    display: flex;
    gap: 15px;
}

/* Stats Cards */
.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    font-size: 24px;
}

.stat-icon.primary { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(76, 201, 240, 0.15); color: var(--success); }
.stat-icon.warning { background: rgba(247, 37, 133, 0.15); color: var(--warning); }
.stat-icon.info { background: rgba(72, 149, 239, 0.15); color: var(--info); }

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    color: #6c757d;
    font-weight: 500;
}

/* Table Container */
.table-container {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    margin-top: 20px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.table-header h3 {
    color: var(--dark);
    font-weight: 700;
    margin-bottom: 0;
}

.table-actions {
    display: flex;
    gap: 10px;
}

/* Buttons */
.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
}

.btn-info {
    background: linear-gradient(135deg, var(--info), #3a86ff);
    border: none;
    border-radius: 10px;
    padding: 10px 20px;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(72, 149, 239, 0.3);
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(72, 149, 239, 0.4);
}

.btn-success, .btn-danger {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 70px;
}

.btn-success:hover, .btn-danger:hover {
    transform: translateY(-2px);
}

.btn-warning {
    background: linear-gradient(135deg, #ff9e00, #ff6b00);
    border: none;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    min-width: 60px;
}

.btn-warning:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, #ff6b00, #ff9e00);
}

/* Table */
.table {
    margin-bottom: 0;
    width: 100%;
}

.table th {
    background: rgba(67, 97, 238, 0.1);
    color: var(--primary);
    font-weight: 600;
    border: none;
    padding: 15px 12px;
    text-align: center;
}

.table td {
    padding: 15px 12px;
    vertical-align: middle;
    border-color: rgba(0, 0, 0, 0.05);
    text-align: center;
}

.table tbody tr {
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background: rgba(67, 97, 238, 0.05);
    transform: translateX(5px);
}

/* Fix alignment for student photos and QR codes */
.photo-img, .qr-img {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid rgba(67, 97, 238, 0.2);
    transition: all 0.3s ease;
    display: block;
    margin: 0 auto;
    max-width: 100%;
}

.default-placeholder {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #dee2e6;
    color: #6c757d;
    font-size: 20px;
    margin: 0 auto;
}

/* Center table cells content */
.table td {
    vertical-align: middle;
    text-align: center;
}

/* Fix alignment for photo and QR columns specifically */
.table td:nth-child(4), /* Photo column */
.table td:nth-child(5) { /* QR column */
    text-align: center;
    width: 80px; /* Fixed width for image columns */
}

/* Name and Email columns */
.table td:nth-child(2), /* Name column */
.table td:nth-child(3) { /* Email column */
    text-align: left;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Badges */
.badge {
    padding: 8px 14px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.85rem;
}

/* Attendance Actions */
.attendance-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
}

.attendance-form {
    display: inline;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
    justify-content: center;
}

/* Modal */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    color: white;
    border-bottom: none;
    padding: 20px 25px;
}

.modal-title {
    font-weight: 700;
}

.modal-body {
    padding: 25px;
}

/* QR Scanner */
#reader {
    width: 100% !important;
    border-radius: 12px;
    overflow: hidden;
    margin: 0 auto;
}

/* Mobile Card View */
.mobile-students-view {
    display: none;
}

.student-card {
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.1);
    margin-bottom: 15px;
    transition: all 0.3s ease;
    overflow: hidden;
}

.student-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}

.student-images {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}

.student-images .photo-img,
.student-images .qr-img {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    object-fit: cover;
    border: 2px solid rgba(67, 97, 238, 0.2);
}

.student-images .default-placeholder {
    width: 60px;
    height: 60px;
    border-radius: 10px;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px dashed #dee2e6;
    color: #6c757d;
    font-size: 20px;
}

.mobile-attendance-actions {
    display: flex;
    gap: 8px;
    margin: 10px 0;
    flex-wrap: wrap;
}

.mobile-attendance-actions .btn {
    flex: 1;
    min-width: 120px;
}

.mobile-action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.mobile-action-buttons .btn {
    flex: 1;
    min-width: 100px;
}

/* Change Status Button */
.change-status-btn {
    background: linear-gradient(135deg, #6c757d, #495057);
    border: none;
    border-radius: 8px;
    color: white;
    padding: 6px 12px;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.change-status-btn:hover {
    background: linear-gradient(135deg, #495057, #343a40);
    transform: translateY(-2px);
    color: white;
}

/* Mobile Card View Improvements */
.student-card .card-body {
    padding: 20px;
}

.student-card .row.align-items-start {
    align-items: flex-start !important;
}

/* Better spacing for mobile cards */
.mobile-students-view .col-8 {
    padding-right: 10px;
}

.mobile-students-view .col-4 {
    padding-left: 10px;
}

/* Ensure proper text alignment */
.student-card .card-title {
    font-size: 1.1rem;
    line-height: 1.3;
    margin-bottom: 8px;
}

.student-card .card-text {
    font-size: 0.85rem;
    line-height: 1.4;
    margin-bottom: 5px;
}

/* Responsive */
@media (max-width: 1200px) {
    .table td:nth-child(2),
    .table td:nth-child(3) {
        max-width: 150px;
    }
}

@media (max-width: 992px) {
    .sidebar {
        width: 80px;
        overflow: visible;
    }
    
    .sidebar-brand h2, .sidebar-nav span {
        display: none;
    }
    
    .sidebar-nav a {
        justify-content: center;
        padding: 15px;
    }
    
    .sidebar-nav i {
        margin-right: 0;
        font-size: 20px;
    }
    
    .main-content {
        margin-left: 80px;
        padding: 15px;
    }
    
    .sidebar-nav a:hover span {
        display: block;
        position: absolute;
        left: 85px;
        background: rgba(0, 0, 0, 0.8);
        padding: 8px 15px;
        border-radius: 6px;
        color: white;
        white-space: nowrap;
        z-index: 1000;
    }
    
    /* Switch to mobile card view */
    .table-responsive {
        display: none;
    }
    
    .mobile-students-view {
        display: block;
    }
    
    .dashboard-header h1 {
        font-size: 28px;
    }
    
    .stats-container {
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .stat-card {
        padding: 20px;
    }
}

@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
    }
    
    .sidebar-content {
        padding: 15px;
    }
    
    .sidebar-brand {
        margin-bottom: 15px;
        justify-content: center;
    }
    
    .sidebar-nav {
        display: flex;
        justify-content: space-around;
        flex-wrap: wrap;
    }
    
    .sidebar-nav li {
        margin-bottom: 5px;
    }
    
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
    
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-actions {
        margin-top: 15px;
        width: 100%;
        justify-content: space-between;
    }
    
    .stats-container {
        grid-template-columns: 1fr 1fr;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .table-actions {
        margin-top: 15px;
        width: 100%;
        justify-content: space-between;
    }
    
    .photo-img, .qr-img {
        width: 45px;
        height: 45px;
    }
    
    .default-placeholder {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 28px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    
    .mobile-attendance-actions .btn,
    .mobile-action-buttons .btn {
        min-width: 100px;
        font-size: 0.8rem;
    }
    
    .student-images .photo-img,
    .student-images .qr-img {
        width: 50px;
        height: 50px;
    }
    
    .student-images .default-placeholder {
        width: 50px;
        height: 50px;
        font-size: 18px;
    }
}

@media (max-width: 576px) {
    .header-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .header-actions .btn {
        width: 100%;
    }
    
    .table-actions {
        flex-direction: column;
        gap: 10px;
    }
    
    .table-actions .input-group {
        max-width: 100% !important;
    }
    
    .mobile-attendance-actions {
        flex-direction: column;
    }
    
    .mobile-action-buttons {
        flex-direction: column;
    }
    
    .student-images {
        justify-content: center;
        margin-top: 10px;
    }
    
    .stats-container {
        grid-template-columns: 1fr;
    }
    
    .photo-img, .qr-img {
        width: 40px;
        height: 40px;
    }
    
    .default-placeholder {
        width: 40px;
        height: 40px;
        font-size: 16px;
    }
    
    .dashboard-header h1 {
        font-size: 24px;
    }
    
    .table-container {
        padding: 15px;
    }
    
    .main-content {
        padding: 10px;
    }
    
    .student-card .col-8 {
        padding-right: 8px;
    }
    
    .student-card .col-4 {
        padding-left: 8px;
    }
    
    .student-images .photo-img,
    .student-images .qr-img {
        width: 45px;
        height: 45px;
    }
    
    .student-images .default-placeholder {
        width: 45px;
        height: 45px;
        font-size: 16px;
    }
    
    .student-card .card-title {
        font-size: 1rem;
    }
    
    .student-card .card-text {
        font-size: 0.8rem;
    }
}

/* Extra small devices (phones, 360px and down) */
@media (max-width: 360px) {
    .student-images {
        flex-direction: column;
        align-items: center;
    }
    
    .photo-img, .qr-img {
        width: 35px;
        height: 35px;
    }
    
    .default-placeholder {
        width: 35px;
        height: 35px;
        font-size: 14px;
    }
    
    .mobile-attendance-actions .btn,
    .mobile-action-buttons .btn {
        min-width: 80px;
        font-size: 0.75rem;
        padding: 6px 8px;
    }
    
    .stat-card {
        padding: 12px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .stat-icon {
        width: 45px;
        height: 45px;
        font-size: 18px;
    }
    
    .student-card .col-8 {
        width: 70%;
    }
    
    .student-card .col-4 {
        width: 30%;
    }
    
    .student-images .photo-img,
    .student-images .qr-img {
        width: 40px;
        height: 40px;
    }
    
    .student-images .default-placeholder {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
}

/* Ensure buttons are always visible and clickable */
.btn {
    min-height: 38px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}

.btn-sm {
    min-height: 32px;
}

/* Make sure forms don't break layout */
.attendance-form {
    display: flex;
}

/* Improve touch targets for mobile */
@media (max-width: 768px) {
    .table td {
        padding: 12px 8px;
    }
    
    .btn {
        padding: 8px 12px;
    }
    
    .btn-sm {
        padding: 6px 10px;
    }
}

/* Animation classes */
.fade-in {
    animation: fadeIn 0.5s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.slide-in {
    animation: slideIn 0.5s ease;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateX(-20px); }
    to { opacity: 1; transform: translateX(0); }
}

/* Hide mobile view on desktop */
@media (min-width: 993px) {
    .mobile-students-view {
        display: none !important;
    }
}

/* Loading states */
.loading {
    opacity: 0.7;
    pointer-events: none;
}

/* Success animations */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.pulse {
    animation: pulse 2s infinite;
}

/* Prevent horizontal scrolling */
html, body {
    max-width: 100%;
    overflow-x: hidden;
}

/* Ensure images don't overflow */
img {
    max-width: 100%;
    height: auto;
}
</style>
</head>
<body>
<!-- Background Animation -->
<div class="background-animation"></div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-content">
        <div class="sidebar-brand">
            <i class="fas fa-graduation-cap"></i>
            <h2>TEAM L</h2>
        </div>
        <ul class="sidebar-nav">
            <li>
                <a href="dashboard.php" class="active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="add_student.php">
                    <i class="fas fa-user-plus"></i>
                    <span>Add Student</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li>
                <a href="logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="dashboard-header">
        <h1>Student Attendance Dashboard</h1>
        <div class="header-actions">
            <button class="btn btn-primary" id="openModalBtn">
                <i class="fas fa-qrcode me-2"></i> Scan QR
            </button>
            <button class="btn btn-info" id="viewAllHistoryBtn">
                <i class="fas fa-history me-2"></i> View History
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-container">
        <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        
        <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-icon success">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value"><?= $present_today ?></div>
            <div class="stat-label">Present Today</div>
        </div>
        
        <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-icon warning">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-value"><?= $absent_today ?></div>
            <div class="stat-label">Absent Today</div>
        </div>
        
        <div class="stat-card fade-in" data-aos="fade-up" data-aos-delay="400">
            <div class="stat-icon info">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?= $attendance_percentage ?>%</div>
            <div class="stat-label">Attendance Rate</div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="table-container fade-in" data-aos="fade-up" data-aos-delay="500">
        <div class="table-header">
            <h3>Student Records</h3>
            <div class="table-actions">
                <div class="input-group" style="max-width: 300px;">
                    <input type="text" class="form-control" placeholder="Search students..." id="searchInput">
                    <button class="btn btn-outline-primary" type="button" id="searchButton">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Desktop Table View -->
        <div class="table-responsive d-none d-lg-block">
            <table class="table table-hover" id="studentsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Photo</th>
                        <th>QR Code</th>
                        <th>Attendance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $students->data_seek(0); // Reset pointer
                    while($s = $students->fetch_assoc()): 
                        $studentPhoto = getStudentPhoto($s['student_number']);
                        $studentQR = getQRCode($s['student_number']);
                    ?>
                    <tr class="slide-in">
                        <td><?= htmlspecialchars($s['id']) ?></td>
                        <td style="text-align: left;">
                            <strong><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($s['student_number']) ?></small>
                        </td>
                        <td style="text-align: left;"><?= htmlspecialchars($s['email'] ?? 'N/A') ?></td>
                        <td>
                            <?php if ($studentPhoto): ?>
                                <img src="<?= htmlspecialchars($studentPhoto) ?>" class="photo-img" alt="Student Photo" title="Student Photo">
                            <?php else: ?>
                                <div class="default-placeholder" title="No Photo">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($studentQR): ?>
                                <img src="<?= htmlspecialchars($studentQR) ?>" class="qr-img" alt="QR Code" title="QR Code">
                            <?php else: ?>
                                <div class="default-placeholder" title="No QR Code">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php 
                            $student_number = $s['student_number'];
                            if(isset($attendance_status[$student_number])): 
                                if($attendance_status[$student_number] === 'Present'): ?>
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <span class="badge bg-success pulse">Present</span>
                                        <form method="post" class="attendance-form m-0">
                                            <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                            <input type="hidden" name="status" value="Absent">
                                            <button type="submit" class="btn btn-outline-danger btn-sm change-status-btn" title="Mark as Absent">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex align-items-center gap-2 justify-content-center">
                                        <span class="badge bg-danger">Absent</span>
                                        <form method="post" class="attendance-form m-0">
                                            <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                            <input type="hidden" name="status" value="Present">
                                            <button type="submit" class="btn btn-outline-success btn-sm change-status-btn" title="Mark as Present">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="attendance-actions">
                                    <form method="post" class="attendance-form">
                                        <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                        <input type="hidden" name="status" value="Present">
                                        <button type="submit" class="btn btn-success btn-sm">
                                            <i class="fas fa-check me-1"></i>Present
                                        </button>
                                    </form>
                                    <form method="post" class="attendance-form">
                                        <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                        <input type="hidden" name="status" value="Absent">
                                        <button type="submit" class="btn btn-danger btn-sm">
                                            <i class="fas fa-times me-1"></i>Absent
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="edit_student.php?id=<?= $s['id'] ?>" class="btn btn-warning btn-sm" title="Edit Student">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" action="delete_student.php" onsubmit="return confirm('Are you sure you want to delete this student?');" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" title="Delete Student">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Mobile Card View -->
        <div class="mobile-students-view">
            <div class="row" id="mobileStudentsList">
                <?php 
                $students->data_seek(0); // Reset pointer
                while($s = $students->fetch_assoc()): 
                    $studentPhoto = getStudentPhoto($s['student_number']);
                    $studentQR = getQRCode($s['student_number']);
                ?>
                <div class="col-12 mb-3">
                    <div class="card student-card">
                        <div class="card-body">
                            <div class="row align-items-start">
                                <!-- Student Info Column -->
                                <div class="col-8">
                                    <h6 class="card-title mb-1 fw-bold"><?= htmlspecialchars($s['firstname'] . ' ' . $s['lastname']) ?></h6>
                                    <p class="card-text text-muted small mb-1">ID: <?= htmlspecialchars($s['id']) ?> | <?= htmlspecialchars($s['student_number']) ?></p>
                                    <p class="card-text text-muted small mb-2"><?= htmlspecialchars($s['email'] ?? 'N/A') ?></p>
                                </div>
                                
                                <!-- Photo & QR Column -->
                                <div class="col-4">
                                    <div class="student-images d-flex flex-column align-items-center gap-2">
                                        <?php if ($studentPhoto): ?>
                                            <img src="<?= htmlspecialchars($studentPhoto) ?>" class="photo-img" alt="Student Photo" title="Student Photo">
                                        <?php else: ?>
                                            <div class="default-placeholder" title="No Photo">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($studentQR): ?>
                                            <img src="<?= htmlspecialchars($studentQR) ?>" class="qr-img" alt="QR Code" title="QR Code">
                                        <?php else: ?>
                                            <div class="default-placeholder" title="No QR Code">
                                                <i class="fas fa-qrcode"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="attendance-section mb-3 mt-3">
                                <?php 
                                $student_number = $s['student_number'];
                                if(isset($attendance_status[$student_number])): 
                                    if($attendance_status[$student_number] === 'Present'): ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-success flex-grow-1 text-center pulse">Present Today</span>
                                        </div>
                                        <div class="mobile-attendance-actions">
                                            <form method="post" class="attendance-form w-100">
                                                <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                                <input type="hidden" name="status" value="Absent">
                                                <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                                    <i class="fas fa-times me-1"></i> Mark Absent
                                                </button>
                                            </form>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <span class="badge bg-danger flex-grow-1 text-center">Absent Today</span>
                                        </div>
                                        <div class="mobile-attendance-actions">
                                            <form method="post" class="attendance-form w-100">
                                                <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                                <input type="hidden" name="status" value="Present">
                                                <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                                    <i class="fas fa-check me-1"></i> Mark Present
                                                </button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="mobile-attendance-actions">
                                        <form method="post" class="attendance-form w-100">
                                            <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                            <input type="hidden" name="status" value="Present">
                                            <button type="submit" class="btn btn-success btn-sm w-100">
                                                <i class="fas fa-check me-1"></i> Present
                                            </button>
                                        </form>
                                        <form method="post" class="attendance-form w-100">
                                            <input type="hidden" name="student_number" value="<?= htmlspecialchars($student_number) ?>">
                                            <input type="hidden" name="status" value="Absent">
                                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                                <i class="fas fa-times me-1"></i> Absent
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mobile-action-buttons">
                                <a href="edit_student.php?id=<?= $s['id'] ?>" class="btn btn-warning btn-sm flex-fill">
                                    <i class="fas fa-edit me-1"></i> Edit
                                </a>
                                <form method="post" action="delete_student.php" onsubmit="return confirm('Are you sure you want to delete this student?');" class="flex-fill">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm w-100">
                                        <i class="fas fa-trash me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Scan QR to Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <div id="reader" class="mb-3"></div>
                <form method="post" id="attendance-form">
                    <input type="hidden" name="student_number" id="student_number">
                    <input type="hidden" name="status" value="Present">
                </form>
                <p class="message mt-3 fw-bold" id="scanMessage"></p>
            </div>
        </div>
    </div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyTitle">Attendance History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="history-list" id="historyContent">Loading...</div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS Animation Library -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>
// Initialize AOS animations
AOS.init({
    duration: 800,
    once: true
});

// QR Scanner
let html5QrcodeScanner = null;

function onScanSuccess(decodedText){
    const studentNumber = decodedText.split('|')[0];
    if(studentNumber){
        document.getElementById('student_number').value = studentNumber;
        document.getElementById('scanMessage').innerText = "✅ Attendance marked successfully!";
        document.getElementById('scanMessage').className = "message text-success";
        
        // Submit form after a short delay to show success message
        setTimeout(() => {
            document.getElementById('attendance-form').submit();
        }, 1500);
    } else {
        document.getElementById('scanMessage').innerText = "⚠️ Invalid QR code!";
        document.getElementById('scanMessage').className = "message text-danger";
    }
}

function onScanFailure(error) {
    // Handle scan failure, but don't show errors in console
}

// Initialize QR scanner when modal is shown
document.getElementById('qrModal').addEventListener('shown.bs.modal', function () {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear();
    }
    
    html5QrcodeScanner = new Html5QrcodeScanner(
        "reader", 
        { 
            fps: 10, 
            qrbox: { width: 250, height: 250 },
            supportedScanTypes: [
                Html5QrcodeScanType.SCAN_TYPE_CAMERA
            ]
        },
        false
    );
    html5QrcodeScanner.render(onScanSuccess, onScanFailure);
});

// Clear scanner when modal is hidden
document.getElementById('qrModal').addEventListener('hidden.bs.modal', function () {
    if (html5QrcodeScanner) {
        html5QrcodeScanner.clear().catch(error => {
            console.error("Failed to clear html5QrcodeScanner. ", error);
        });
        html5QrcodeScanner = null;
    }
    document.getElementById('scanMessage').innerText = "";
});

// View All History
document.getElementById('viewAllHistoryBtn').addEventListener('click', function() {
    const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
    document.getElementById("historyTitle").innerText = "All Students Attendance History";
    document.getElementById("historyContent").innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading attendance history...</p>
        </div>
    `;
    
    fetch("view_all_history.php")
        .then(res => res.text())
        .then(html => document.getElementById("historyContent").innerHTML = html)
        .catch(() => document.getElementById("historyContent").innerHTML = `
            <div class="alert alert-danger text-center">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Error loading attendance history.
            </div>
        `);
    
    historyModal.show();
});

// Search functionality
function performSearch(searchTerm) {
    searchTerm = searchTerm.toLowerCase().trim();
    
    // Search in table view
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    let visibleRows = 0;
    
    rows.forEach(row => {
        const name = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
        const email = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            row.style.display = '';
            visibleRows++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Search in mobile card view
    const cards = document.querySelectorAll('.student-card');
    cards.forEach(card => {
        const name = card.querySelector('.card-title').textContent.toLowerCase();
        const email = card.querySelector('.card-text.text-muted.small:last-child').textContent.toLowerCase();
        
        if (name.includes(searchTerm) || email.includes(searchTerm)) {
            card.closest('.col-12').style.display = '';
        } else {
            card.closest('.col-12').style.display = 'none';
        }
    });
    
    // Show no results message if needed
    const noResults = document.getElementById('noResults');
    if (searchTerm && visibleRows === 0) {
        if (!noResults) {
            const noResultsDiv = document.createElement('div');
            noResultsDiv.id = 'noResults';
            noResultsDiv.className = 'alert alert-info text-center mt-3';
            noResultsDiv.innerHTML = `<i class="fas fa-info-circle me-2"></i>No students found matching "${searchTerm}"`;
            document.querySelector('.table-container').appendChild(noResultsDiv);
        }
    } else if (noResults) {
        noResults.remove();
    }
}

// Event listeners for search
document.getElementById('searchInput').addEventListener('input', function(e) {
    performSearch(e.target.value);
});

document.getElementById('searchButton').addEventListener('click', function() {
    performSearch(document.getElementById('searchInput').value);
});

// Clear search when pressing Escape
document.getElementById('searchInput').addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        this.value = '';
        performSearch('');
        this.focus();
    }
});

// Open QR Modal
document.getElementById('openModalBtn').addEventListener('click', function() {
    const qrModal = new bootstrap.Modal(document.getElementById('qrModal'));
    document.getElementById('scanMessage').innerText = "";
    qrModal.show();
});

// Add loading state to forms
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
            
            // Revert after 3 seconds in case of error
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        }
    });
});

// Auto-refresh page every 30 seconds to update attendance status
setTimeout(() => {
    window.location.reload();
}, 30000);

console.log('Dashboard loaded successfully');
</script>
</body>
</html>