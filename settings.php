<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        // Update general settings
        $school_name = trim($_POST['school_name']);
        $attendance_time = trim($_POST['attendance_time']);
        $auto_mark_absent = isset($_POST['auto_mark_absent']) ? 1 : 0;
        $qr_code_enabled = isset($_POST['qr_code_enabled']) ? 1 : 0;
        
        // Update settings in database
        $stmt = $conn->prepare("UPDATE settings SET school_name=?, attendance_time=?, auto_mark_absent=?, qr_code_enabled=? WHERE id=1");
        $stmt->bind_param("ssii", $school_name, $attendance_time, $auto_mark_absent, $qr_code_enabled);
        
        if ($stmt->execute()) {
            $message = "Settings updated successfully!";
            $message_type = "success";
        } else {
            $message = "Error updating settings: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
        
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate passwords
        if ($new_password !== $confirm_password) {
            $message = "New passwords do not match!";
            $message_type = "danger";
        } elseif (strlen($new_password) < 6) {
            $message = "New password must be at least 6 characters long!";
            $message_type = "danger";
        } else {
            // Get current user's password
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result($hashed_password);
            $stmt->fetch();
            $stmt->close();
            
            // Verify current password
            if (password_verify($current_password, $hashed_password)) {
                // Update password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $new_hashed_password, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Password changed successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error changing password!";
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Current password is incorrect!";
                $message_type = "danger";
            }
        }
        
    } elseif (isset($_POST['export_data'])) {
        // Export data functionality
        $export_type = $_POST['export_type'];
        exportData($export_type);
        exit;
        
    } elseif (isset($_POST['reset_all'])) {
        // Reset all data
        resetAllData();
        $message = "All data has been reset successfully!";
        $message_type = "success";
    }
}

// Get current settings
$settings_result = $conn->query("SELECT * FROM settings WHERE id = 1");
if ($settings_result->num_rows > 0) {
    $settings = $settings_result->fetch_assoc();
} else {
    // Create default settings if not exists
    $default_school_name = "Our School";
    $default_attendance_time = "09:00";
    $default_auto_mark_absent = 1;
    $default_qr_code_enabled = 1;
    
    $stmt = $conn->prepare("INSERT INTO settings (school_name, attendance_time, auto_mark_absent, qr_code_enabled) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssii", $default_school_name, $default_attendance_time, $default_auto_mark_absent, $default_qr_code_enabled);
    $stmt->execute();
    $stmt->close();
    
    $settings = [
        'school_name' => $default_school_name,
        'attendance_time' => $default_attendance_time,
        'auto_mark_absent' => $default_auto_mark_absent,
        'qr_code_enabled' => $default_qr_code_enabled
    ];
}

// Get system statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM students")->fetch_assoc()['count'];
$total_attendance = $conn->query("SELECT COUNT(*) as count FROM attendance")->fetch_assoc()['count'];
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];

// Export data function
function exportData($type) {
    global $conn;
    
    $filename = "";
    $data = "";
    
    switch($type) {
        case 'students':
            $filename = "students_export_" . date('Y-m-d') . ".csv";
            $result = $conn->query("SELECT * FROM students");
            
            $data = "ID,Student Number,First Name,Last Name,Email,Phone,Address,Created At\n";
            while($row = $result->fetch_assoc()) {
                $data .= "{$row['id']},{$row['student_number']},{$row['firstname']},{$row['lastname']},{$row['email']},{$row['phone']},{$row['address']},{$row['created_at']}\n";
            }
            break;
            
        case 'attendance':
            $filename = "attendance_export_" . date('Y-m-d') . ".csv";
            $result = $conn->query("
                SELECT a.*, s.firstname, s.lastname, s.student_number 
                FROM attendance a 
                JOIN students s ON a.student_number = s.student_number 
                ORDER BY a.date DESC
            ");
            
            $data = "ID,Student Number,Name,Date,Status,Recorded At\n";
            while($row = $result->fetch_assoc()) {
                $fullname = $row['firstname'] . ' ' . $row['lastname'];
                $data .= "{$row['id']},{$row['student_number']},{$fullname},{$row['date']},{$row['status']},{$row['recorded_at']}\n";
            }
            break;
            
        case 'reports':
            $filename = "reports_export_" . date('Y-m-d') . ".csv";
            $result = $conn->query("
                SELECT 
                    s.student_number,
                    CONCAT(s.firstname, ' ', s.lastname) as student_name,
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
                    COUNT(a.id) as total_records,
                    ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2) as attendance_rate
                FROM students s
                LEFT JOIN attendance a ON s.student_number = a.student_number
                GROUP BY s.student_number, student_name
                ORDER BY attendance_rate DESC
            ");
            
            $data = "Student Number,Student Name,Present Days,Absent Days,Total Records,Attendance Rate(%)\n";
            while($row = $result->fetch_assoc()) {
                $data .= "{$row['student_number']},{$row['student_name']},{$row['present_count']},{$row['absent_count']},{$row['total_records']},{$row['attendance_rate']}\n";
            }
            break;
    }
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $data;
    exit;
}

// Reset all data function
function resetAllData() {
    global $conn;
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete all attendance records
        $conn->query("DELETE FROM attendance");
        
        // Delete all students
        $conn->query("DELETE FROM students");
        
        // Reset auto-increment counters
        $conn->query("ALTER TABLE attendance AUTO_INCREMENT = 1");
        $conn->query("ALTER TABLE students AUTO_INCREMENT = 1");
        
        // Delete uploaded files
        $upload_dirs = ['uploads/', 'qrcodes/'];
        foreach ($upload_dirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
        }
        
        // Reset settings to default
        $default_school_name = "Our School";
        $default_attendance_time = "09:00";
        $default_auto_mark_absent = 1;
        $default_qr_code_enabled = 1;
        
        $stmt = $conn->prepare("UPDATE settings SET school_name=?, attendance_time=?, auto_mark_absent=?, qr_code_enabled=? WHERE id=1");
        $stmt->bind_param("ssii", $default_school_name, $default_attendance_time, $default_auto_mark_absent, $default_qr_code_enabled);
        $stmt->execute();
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        return true;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Student Attendance System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    /* Settings Container */
    .settings-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    .settings-section {
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    }

    .settings-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .section-title {
        color: var(--primary);
        font-weight: 700;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--primary);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        font-size: 24px;
    }

    /* Form Styles */
    .form-label {
        font-weight: 600;
        color: #495057;
        margin-bottom: 8px;
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #dee2e6;
        padding: 12px 15px;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
    }

    /* Buttons */
    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        border-radius: 10px;
        padding: 12px 25px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3);
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), #3a0ca3);
        border: none;
        border-radius: 10px;
        padding: 12px 25px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(76, 201, 240, 0.3);
        transition: all 0.3s ease;
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(76, 201, 240, 0.4);
    }

    .btn-warning {
        background: linear-gradient(135deg, #ff9e00, #ff6b00);
        border: none;
        border-radius: 10px;
        padding: 12px 25px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(255, 158, 0, 0.3);
        transition: all 0.3s ease;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 158, 0, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc3545, #c82333);
        border: none;
        border-radius: 10px;
        padding: 12px 25px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        transition: all 0.3s ease;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
    }

    /* Stats Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid rgba(255, 255, 255, 0.3);
        text-align: center;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 20px;
    }

    .stat-icon.primary { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
    .stat-icon.success { background: rgba(76, 201, 240, 0.15); color: var(--success); }
    .stat-icon.warning { background: rgba(247, 37, 133, 0.15); color: var(--warning); }
    .stat-icon.info { background: rgba(72, 149, 239, 0.15); color: var(--info); }
    .stat-icon.danger { background: rgba(220, 53, 69, 0.15); color: #dc3545; }

    .stat-value {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 5px;
        color: var(--dark);
    }

    .stat-label {
        color: #6c757d;
        font-weight: 500;
        font-size: 0.9rem;
    }

    /* Toggle Switch */
    .form-check-input:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }

    .form-check-input:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
    }

    /* Alert */
    .alert {
        border-radius: 10px;
        border: none;
        padding: 15px 20px;
        margin-bottom: 20px;
    }

    /* Danger Zone */
    .danger-zone {
        background: linear-gradient(135deg, #fff5f5, #ffe6e6);
        border: 2px solid #dc3545;
        border-radius: 12px;
        padding: 25px;
    }

    .danger-zone .section-title {
        color: #dc3545;
        border-bottom-color: #dc3545;
    }

    /* Responsive */
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
        }
        
        .sidebar-nav li {
            margin-bottom: 0;
        }
        
        .main-content {
            margin-left: 0;
            padding: 15px;
        }
        
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .settings-container {
            padding: 20px;
        }
        
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 576px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .section-title {
            font-size: 1.3rem;
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
                <a href="dashboard.php">
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
                <a href="settings.php" class="active">
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
        <h1>System Settings</h1>
    </div>

    <!-- System Statistics -->
    <div class="stats-container fade-in">
        <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $total_students ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        
        <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
            <div class="stat-icon success">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <div class="stat-value"><?= $total_attendance ?></div>
            <div class="stat-label">Attendance Records</div>
        </div>
        
        <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
            <div class="stat-icon warning">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="stat-value"><?= $total_users ?></div>
            <div class="stat-label">System Users</div>
        </div>
        
        <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
            <div class="stat-icon info">
                <i class="fas fa-database"></i>
            </div>
            <div class="stat-value">v2.1</div>
            <div class="stat-label">System Version</div>
        </div>
    </div>

    <!-- Settings Container -->
    <div class="settings-container fade-in" data-aos="fade-up">
        <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?= $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- General Settings -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-cogs"></i>
                General Settings
            </h3>
            <form method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="school_name" class="form-label">School Name</label>
                        <input type="text" class="form-control" id="school_name" name="school_name" 
                               value="<?= htmlspecialchars($settings['school_name']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="attendance_time" class="form-label">Default Attendance Time</label>
                        <input type="time" class="form-control" id="attendance_time" name="attendance_time" 
                               value="<?= htmlspecialchars($settings['attendance_time']) ?>" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="auto_mark_absent" name="auto_mark_absent" 
                                   <?= $settings['auto_mark_absent'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="auto_mark_absent">
                                Auto Mark Absent Students
                            </label>
                        </div>
                        <small class="text-muted">Automatically mark unmarked students as absent at the end of the day</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="qr_code_enabled" name="qr_code_enabled" 
                                   <?= $settings['qr_code_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="qr_code_enabled">
                                Enable QR Code Attendance
                            </label>
                        </div>
                        <small class="text-muted">Allow students to mark attendance using QR codes</small>
                    </div>
                </div>
                
                <button type="submit" name="update_settings" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
            </form>
        </div>

        <!-- Password Change -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-lock"></i>
                Change Password
            </h3>
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn btn-warning">
                    <i class="fas fa-key me-2"></i>Change Password
                </button>
            </form>
        </div>

        <!-- Data Management -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-database"></i>
                Data Management
            </h3>
            <div class="row">
                <div class="col-md-8">
                    <p class="mb-3">Export system data for backup or analysis purposes.</p>
                    <form method="POST" class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label for="export_type" class="form-label">Export Data Type</label>
                            <select class="form-select" id="export_type" name="export_type" required>
                                <option value="">Select data to export...</option>
                                <option value="students">Students Data</option>
                                <option value="attendance">Attendance Records</option>
                                <option value="reports">Attendance Reports</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button type="submit" name="export_data" class="btn btn-success w-100">
                                <i class="fas fa-download me-2"></i>Export Data
                            </button>
                        </div>
                    </form>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body text-center">
                            <i class="fas fa-exclamation-triangle text-warning mb-3" style="font-size: 2rem;"></i>
                            <h6 class="card-title">System Maintenance</h6>
                            <p class="card-text small text-muted">For database backup and system maintenance, please contact your system administrator.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div class="settings-section danger-zone">
            <h3 class="section-title">
                <i class="fas fa-exclamation-triangle"></i>
                Danger Zone
            </h3>
            <div class="row">
                <div class="col-md-8">
                    <h5 class="text-danger mb-3">Reset All Data</h5>
                    <p class="text-muted mb-4">
                        <strong>Warning:</strong> This action will permanently delete all students, attendance records, and uploaded files. 
                        This action cannot be undone. Please make sure you have exported any important data before proceeding.
                    </p>
                    <div class="alert alert-danger">
                        <i class="fas fa-radiation me-2"></i>
                        <strong>This will delete:</strong>
                        <ul class="mb-0 mt-2">
                            <li>All student records (<?= $total_students ?> students)</li>
                            <li>All attendance records (<?= $total_attendance ?> records)</li>
                            <li>All uploaded photos and QR codes</li>
                            <li>Reset settings to default</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-center justify-content-center">
                    <form method="POST" onsubmit="return confirmReset()">
                        <button type="submit" name="reset_all" class="btn btn-danger btn-lg">
                            <i class="fas fa-bomb me-2"></i>Reset All Data
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="settings-section">
            <h3 class="section-title">
                <i class="fas fa-info-circle"></i>
                System Information
            </h3>
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-server me-2"></i>Server Information</h6>
                            <ul class="list-unstyled mt-3">
                                <li class="mb-2"><strong>PHP Version:</strong> <?= phpversion() ?></li>
                                <li class="mb-2"><strong>Server Software:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ?></li>
                                <li class="mb-2"><strong>Database:</strong> MySQL</li>
                                <li><strong>Last Updated:</strong> <?= date('Y-m-d H:i:s') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title"><i class="fas fa-mobile-alt me-2"></i>Application Info</h6>
                            <ul class="list-unstyled mt-3">
                                <li class="mb-2"><strong>Version:</strong> 2.1.0</li>
                                <li class="mb-2"><strong>Developer:</strong> EduTrack Team</li>
                                <li class="mb-2"><strong>License:</strong> MIT</li>
                                <li><strong>Support:</strong> support@edutrack.com</li>
                            </ul>
                        </div>
                    </div>
                </div>
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

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    
    function validatePasswords() {
        if (newPassword.value !== confirmPassword.value) {
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.setCustomValidity('');
        }
    }
    
    newPassword.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
});

// Reset confirmation
function confirmReset() {
    const studentCount = <?= $total_students ?>;
    const attendanceCount = <?= $total_attendance ?>;
    
    const message = `🚨 DANGER: This will permanently delete:\n\n` +
                   `• ${studentCount} student records\n` +
                   `• ${attendanceCount} attendance records\n` +
                   `• All uploaded photos and QR codes\n\n` +
                   `This action CANNOT be undone!\n\n` +
                   `Type "RESET" to confirm:`;
    
    const confirmation = prompt(message);
    
    if (confirmation === 'RESET') {
        return true;
    } else {
        alert('Reset cancelled. Data is safe.');
        return false;
    }
}
</script>
</body>
</html>