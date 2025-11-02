<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

// Date range filter
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$student_filter = $_GET['student_filter'] ?? '';

// Validate dates
if (!strtotime($start_date)) $start_date = date('Y-m-01');
if (!strtotime($end_date)) $end_date = date('Y-m-t');
if ($start_date > $end_date) $start_date = $end_date;

// Build query conditions
$where_conditions = ["a.date BETWEEN ? AND ?"];
$params = [$start_date, $end_date];
$param_types = "ss";

if (!empty($student_filter)) {
    $where_conditions[] = "(s.firstname LIKE ? OR s.lastname LIKE ? OR s.student_number LIKE ?)";
    $params[] = "%$student_filter%";
    $params[] = "%$student_filter%";
    $params[] = "%$student_filter%";
    $param_types .= "sss";
}

$where_clause = implode(" AND ", $where_conditions);

// Get attendance summary
$summary_query = "
    SELECT 
        COUNT(DISTINCT s.id) as total_students,
        COUNT(DISTINCT a.date) as total_days,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
        ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2) as overall_attendance_rate
    FROM students s
    LEFT JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
";

$summary_stmt = $conn->prepare($summary_query);
$summary_stmt->bind_param("ss", $start_date, $end_date);
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();
$summary_stmt->close();

// Get student-wise attendance
$student_query = "
    SELECT 
        s.id,
        s.student_number,
        s.firstname,
        s.lastname,
        s.email,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
        COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
        COUNT(a.id) as total_days,
        CASE 
            WHEN COUNT(a.id) > 0 THEN 
                ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2)
            ELSE 0 
        END as attendance_rate
    FROM students s
    LEFT JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
    " . (!empty($student_filter) ? " WHERE (s.firstname LIKE ? OR s.lastname LIKE ? OR s.student_number LIKE ?)" : "") . "
    GROUP BY s.id, s.student_number, s.firstname, s.lastname, s.email
    ORDER BY attendance_rate DESC, s.firstname, s.lastname
";

$student_stmt = $conn->prepare($student_query);
if (!empty($student_filter)) {
    $search_param = "%$student_filter%";
    $student_stmt->bind_param("sssss", $start_date, $end_date, $search_param, $search_param, $search_param);
} else {
    $student_stmt->bind_param("ss", $start_date, $end_date);
}
$student_stmt->execute();
$students_report = $student_stmt->get_result();
$student_stmt->close();

// Get daily attendance summary
$daily_query = "
    SELECT 
        date,
        COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_count,
        COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_count,
        COUNT(*) as total_students,
        ROUND((COUNT(CASE WHEN status = 'Present' THEN 1 END) * 100.0 / COUNT(*)), 2) as daily_rate
    FROM attendance 
    WHERE date BETWEEN ? AND ?
    GROUP BY date 
    ORDER BY date DESC
";

$daily_stmt = $conn->prepare($daily_query);
$daily_stmt->bind_param("ss", $start_date, $end_date);
$daily_stmt->execute();
$daily_report = $daily_stmt->get_result();
$daily_stmt->close();

// Get top performers (attendance rate > 90%)
$top_performers_query = "
    SELECT 
        s.student_number,
        CONCAT(s.firstname, ' ', s.lastname) as student_name,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
        COUNT(a.id) as total_days,
        ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2) as attendance_rate
    FROM students s
    JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
    GROUP BY s.student_number, student_name
    HAVING attendance_rate >= 90
    ORDER BY attendance_rate DESC
    LIMIT 10
";

$top_stmt = $conn->prepare($top_performers_query);
$top_stmt->bind_param("ss", $start_date, $end_date);
$top_stmt->execute();
$top_performers = $top_stmt->get_result();
$top_stmt->close();

// Get students needing attention (attendance rate < 70%)
$attention_query = "
    SELECT 
        s.student_number,
        CONCAT(s.firstname, ' ', s.lastname) as student_name,
        COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
        COUNT(a.id) as total_days,
        ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2) as attendance_rate
    FROM students s
    JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
    GROUP BY s.student_number, student_name
    HAVING attendance_rate < 70
    ORDER BY attendance_rate ASC
    LIMIT 10
";

$attention_stmt = $conn->prepare($attention_query);
$attention_stmt->bind_param("ss", $start_date, $end_date);
$attention_stmt->execute();
$need_attention = $attention_stmt->get_result();
$attention_stmt->close();

// Export functionality
if (isset($_GET['export'])) {
    $export_type = $_GET['export'];
    exportReport($export_type, $start_date, $end_date);
    exit;
}

function exportReport($type, $start_date, $end_date) {
    global $conn;
    
    $filename = "";
    $data = "";
    
    switch($type) {
        case 'summary':
            $filename = "attendance_summary_{$start_date}_to_{$end_date}.csv";
            
            // Get summary data
            $query = "
                SELECT 
                    COUNT(DISTINCT s.id) as total_students,
                    COUNT(DISTINCT a.date) as total_days,
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
                    ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2) as overall_attendance_rate
                FROM students s
                LEFT JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $summary = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $data = "Attendance Summary Report\n";
            $data .= "Period: {$start_date} to {$end_date}\n";
            $data .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
            $data .= "Metric,Value\n";
            $data .= "Total Students,{$summary['total_students']}\n";
            $data .= "Total Days,{$summary['total_days']}\n";
            $data .= "Present Records,{$summary['present_count']}\n";
            $data .= "Absent Records,{$summary['absent_count']}\n";
            $data .= "Overall Attendance Rate,{$summary['overall_attendance_rate']}%\n";
            break;
            
        case 'students':
            $filename = "student_attendance_{$start_date}_to_{$end_date}.csv";
            
            $query = "
                SELECT 
                    s.student_number,
                    CONCAT(s.firstname, ' ', s.lastname) as student_name,
                    s.email,
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_days,
                    COUNT(a.id) as total_days,
                    CASE 
                        WHEN COUNT(a.id) > 0 THEN 
                            ROUND((COUNT(CASE WHEN a.status = 'Present' THEN 1 END) * 100.0 / COUNT(a.id)), 2)
                        ELSE 0 
                    END as attendance_rate
                FROM students s
                LEFT JOIN attendance a ON s.student_number = a.student_number AND a.date BETWEEN ? AND ?
                GROUP BY s.student_number, student_name, s.email
                ORDER BY attendance_rate DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = "Student Attendance Report\n";
            $data .= "Period: {$start_date} to {$end_date}\n";
            $data .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
            $data .= "Student Number,Student Name,Email,Present Days,Absent Days,Total Days,Attendance Rate(%)\n";
            while($row = $result->fetch_assoc()) {
                $data .= "{$row['student_number']},{$row['student_name']},{$row['email']},{$row['present_days']},{$row['absent_days']},{$row['total_days']},{$row['attendance_rate']}\n";
            }
            $stmt->close();
            break;
            
        case 'daily':
            $filename = "daily_attendance_{$start_date}_to_{$end_date}.csv";
            
            $query = "
                SELECT 
                    date,
                    COUNT(CASE WHEN status = 'Present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent_count,
                    COUNT(*) as total_students,
                    ROUND((COUNT(CASE WHEN status = 'Present' THEN 1 END) * 100.0 / COUNT(*)), 2) as daily_rate
                FROM attendance 
                WHERE date BETWEEN ? AND ?
                GROUP BY date 
                ORDER BY date DESC
            ";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ss", $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $data = "Daily Attendance Report\n";
            $data .= "Period: {$start_date} to {$end_date}\n";
            $data .= "Generated on: " . date('Y-m-d H:i:s') . "\n\n";
            $data .= "Date,Present Count,Absent Count,Total Students,Daily Rate(%)\n";
            while($row = $result->fetch_assoc()) {
                $data .= "{$row['date']},{$row['present_count']},{$row['absent_count']},{$row['total_students']},{$row['daily_rate']}\n";
            }
            $stmt->close();
            break;
    }
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $data;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Student Attendance System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        flex-wrap: wrap;
    }

    /* Reports Container */
    .reports-container {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 30px;
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    /* Filter Section */
    .filter-section {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    /* Stats Cards */
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 16px;
        padding: 25px;
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
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 15px;
        font-size: 24px;
    }

    .stat-icon.primary { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
    .stat-icon.success { background: rgba(76, 201, 240, 0.15); color: var(--success); }
    .stat-icon.warning { background: rgba(247, 37, 133, 0.15); color: var(--warning); }
    .stat-icon.info { background: rgba(72, 149, 239, 0.15); color: var(--info); }
    .stat-icon.danger { background: rgba(220, 53, 69, 0.15); color: #dc3545; }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
        color: var(--dark);
    }

    .stat-label {
        color: #6c757d;
        font-weight: 500;
    }

    /* Chart Container */
    .chart-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        height: 400px;
    }

    /* Table Container */
    .table-container {
        background: rgba(255, 255, 255, 0.95);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 30px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        overflow: hidden;
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

    .btn-success {
        background: linear-gradient(135deg, var(--success), #3a0ca3);
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
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
        padding: 10px 20px;
        font-weight: 600;
        box-shadow: 0 4px 15px rgba(255, 158, 0, 0.3);
        transition: all 0.3s ease;
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 158, 0, 0.4);
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

    /* Badges */
    .badge {
        padding: 8px 14px;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    /* Progress bars */
    .progress {
        height: 8px;
        border-radius: 10px;
    }

    .progress-bar {
        border-radius: 10px;
    }

    /* Table */
    .table {
        margin-bottom: 0;
    }

    .table th {
        background: rgba(67, 97, 238, 0.1);
        color: var(--primary);
        font-weight: 600;
        border: none;
        padding: 15px 12px;
    }

    .table td {
        padding: 15px 12px;
        vertical-align: middle;
        border-color: rgba(0, 0, 0, 0.05);
    }

    .table tbody tr {
        transition: all 0.3s ease;
    }

    .table tbody tr:hover {
        background: rgba(67, 97, 238, 0.05);
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
        
        .header-actions {
            margin-top: 15px;
            width: 100%;
            justify-content: space-between;
        }
        
        .stats-container {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .reports-container {
            padding: 20px;
        }
        
        .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .table-header .btn {
            margin-top: 10px;
        }
    }

    @media (max-width: 576px) {
        .stats-container {
            grid-template-columns: 1fr;
        }
        
        .header-actions {
            flex-direction: column;
            gap: 10px;
        }
        
        .header-actions .btn {
            width: 100%;
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

    /* Custom scrollbar */
    ::-webkit-scrollbar {
        width: 8px;
    }

    ::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
        background: var(--primary);
        border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
        background: var(--secondary);
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
                <a href="reports.php" class="active">
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
        <h1>Attendance Reports & Analytics</h1>
        <div class="header-actions">
            <a href="?export=summary&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-success">
                <i class="fas fa-file-export me-2"></i>Export Summary
            </a>
            <a href="?export=students&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-info">
                <i class="fas fa-download me-2"></i>Export Students
            </a>
            <a href="?export=daily&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-warning">
                <i class="fas fa-calendar-alt me-2"></i>Export Daily
            </a>
        </div>
    </div>

    <!-- Filter Section -->
    <div class="filter-section fade-in" data-aos="fade-up">
        <h4 class="mb-4"><i class="fas fa-filter me-2"></i>Filter Reports</h4>
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" value="<?= $start_date ?>">
            </div>
            <div class="col-md-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" value="<?= $end_date ?>">
            </div>
            <div class="col-md-4">
                <label for="student_filter" class="form-label">Search Student</label>
                <input type="text" class="form-control" id="student_filter" name="student_filter" 
                       value="<?= htmlspecialchars($student_filter) ?>" placeholder="Search by name or student number...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Apply
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Statistics -->
    <div class="stats-container fade-in" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?= $summary['total_students'] ?></div>
            <div class="stat-label">Total Students</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-value"><?= $summary['total_days'] ?></div>
            <div class="stat-label">Total Days</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-value"><?= $summary['present_count'] ?></div>
            <div class="stat-label">Present Records</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-value"><?= $summary['absent_count'] ?></div>
            <div class="stat-label">Absent Records</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value"><?= $summary['overall_attendance_rate'] ?>%</div>
            <div class="stat-label">Overall Attendance Rate</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="row fade-in" data-aos="fade-up" data-aos-delay="200">
        <div class="col-md-8">
            <div class="chart-container">
                <h4 class="mb-4"><i class="fas fa-chart-pie me-2"></i>Attendance Distribution</h4>
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-container">
                <h4 class="mb-4"><i class="fas fa-chart-bar me-2"></i>Performance Overview</h4>
                <canvas id="performanceChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Student-wise Attendance Report -->
    <div class="table-container fade-in" data-aos="fade-up" data-aos-delay="300">
        <div class="table-header">
            <h3><i class="fas fa-list-alt me-2"></i>Student-wise Attendance Report</h3>
            <div class="d-flex gap-2">
                <span class="badge bg-primary">Total: <?= $students_report->num_rows ?> students</span>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Student Number</th>
                        <th>Present Days</th>
                        <th>Absent Days</th>
                        <th>Total Days</th>
                        <th>Attendance Rate</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1;
                    while($student = $students_report->fetch_assoc()): 
                        $attendance_rate = $student['attendance_rate'];
                        $performance_class = '';
                        if ($attendance_rate >= 90) {
                            $performance_class = 'bg-success';
                        } elseif ($attendance_rate >= 70) {
                            $performance_class = 'bg-warning';
                        } else {
                            $performance_class = 'bg-danger';
                        }
                    ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td>
                            <strong><?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($student['email']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($student['student_number']) ?></td>
                        <td>
                            <span class="badge bg-success"><?= $student['present_days'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-danger"><?= $student['absent_days'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= $student['total_days'] ?></span>
                        </td>
                        <td>
                            <strong><?= $attendance_rate ?>%</strong>
                        </td>
                        <td>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar <?= $performance_class ?>" 
                                     role="progressbar" 
                                     style="width: <?= $attendance_rate ?>%"
                                     aria-valuenow="<?= $attendance_rate ?>" 
                                     aria-valuemin="0" 
                                     aria-valuemax="100">
                                </div>
                            </div>
                            <small class="text-muted">
                                <?php 
                                if ($attendance_rate >= 90) echo 'Excellent';
                                elseif ($attendance_rate >= 70) echo 'Good';
                                else echo 'Needs Improvement';
                                ?>
                            </small>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Performance Highlights -->
    <div class="row fade-in" data-aos="fade-up" data-aos-delay="400">
        <!-- Top Performers -->
        <div class="col-md-6">
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-trophy me-2 text-warning"></i>Top Performers (>90%)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Present Days</th>
                                <th>Total Days</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($top = $top_performers->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($top['student_name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($top['student_number']) ?></small>
                                </td>
                                <td><?= $top['present_days'] ?></td>
                                <td><?= $top['total_days'] ?></td>
                                <td>
                                    <span class="badge bg-success"><?= $top['attendance_rate'] ?>%</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($top_performers->num_rows == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>No top performers found in the selected period.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Need Attention -->
        <div class="col-md-6">
            <div class="table-container">
                <div class="table-header">
                    <h3><i class="fas fa-exclamation-triangle me-2 text-danger"></i>Need Attention (<70%)</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Present Days</th>
                                <th>Total Days</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($attention = $need_attention->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($attention['student_name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($attention['student_number']) ?></small>
                                </td>
                                <td><?= $attention['present_days'] ?></td>
                                <td><?= $attention['total_days'] ?></td>
                                <td>
                                    <span class="badge bg-danger"><?= $attention['attendance_rate'] ?>%</span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <?php if ($need_attention->num_rows == 0): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="fas fa-info-circle me-2"></i>All students are performing well!
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Attendance Summary -->
    <div class="table-container fade-in" data-aos="fade-up" data-aos-delay="500">
        <div class="table-header">
            <h3><i class="fas fa-calendar-day me-2"></i>Daily Attendance Summary</h3>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Present Students</th>
                        <th>Absent Students</th>
                        <th>Total Students</th>
                        <th>Daily Rate</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($daily = $daily_report->fetch_assoc()): 
                        $daily_rate = $daily['daily_rate'];
                        $status_class = $daily_rate >= 80 ? 'bg-success' : ($daily_rate >= 60 ? 'bg-warning' : 'bg-danger');
                        $status_text = $daily_rate >= 80 ? 'Good' : ($daily_rate >= 60 ? 'Average' : 'Low');
                    ?>
                    <tr>
                        <td>
                            <strong><?= date('M j, Y', strtotime($daily['date'])) ?></strong>
                            <br><small class="text-muted"><?= date('l', strtotime($daily['date'])) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-success"><?= $daily['present_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-danger"><?= $daily['absent_count'] ?></span>
                        </td>
                        <td>
                            <span class="badge bg-primary"><?= $daily['total_students'] ?></span>
                        </td>
                        <td>
                            <strong><?= $daily_rate ?>%</strong>
                        </td>
                        <td>
                            <span class="badge <?= $status_class ?>"><?= $status_text ?></span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($daily_report->num_rows == 0): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-info-circle me-2"></i>No attendance records found for the selected period.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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

// Attendance Distribution Chart
const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(attendanceCtx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent'],
        datasets: [{
            data: [<?= $summary['present_count'] ?>, <?= $summary['absent_count'] ?>],
            backgroundColor: [
                '#4cc9f0',
                '#f72585'
            ],
            borderColor: [
                '#3a0ca3',
                '#7209b7'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Performance Overview Chart
const performanceCtx = document.getElementById('performanceChart').getContext('2d');
const performanceChart = new Chart(performanceCtx, {
    type: 'bar',
    data: {
        labels: ['Excellent (90-100%)', 'Good (70-89%)', 'Needs Improvement (<70%)'],
        datasets: [{
            label: 'Number of Students',
            data: [
                <?= $top_performers->num_rows ?>,
                <?= max(0, $students_report->num_rows - $top_performers->num_rows - $need_attention->num_rows) ?>,
                <?= $need_attention->num_rows ?>
            ],
            backgroundColor: [
                '#4cc9f0',
                '#ff9e00',
                '#f72585'
            ],
            borderColor: [
                '#3a0ca3',
                '#ff6b00',
                '#7209b7'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Auto-submit form when dates change
document.getElementById('start_date').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('end_date').addEventListener('change', function() {
    this.form.submit();
});

// Print functionality
function printReport() {
    window.print();
}
</script>
</body>
</html>