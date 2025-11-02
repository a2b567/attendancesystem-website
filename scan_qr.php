<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';

// Get student_id from QR scan
$student_id = intval($_GET['student_id'] ?? 0);
$date = date('Y-m-d');

if ($student_id <= 0) {
    die("❌ Invalid QR code.");
}

// Check if attendance already exists
$check = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
if (!$check) die("Prepare failed: " . $conn->error);
$check->bind_param("is", $student_id, $date);
$check->execute();
$result = $check->get_result();
if (!$result) die("Get result failed: " . $conn->error);

$attendance_marked = false;
$student_name = "Unknown Student";
$student_email = "N/A";

// Get student details
$student_query = $conn->prepare("SELECT firstname, lastname, email FROM students WHERE id = ?");
if ($student_query) {
    $student_query->bind_param("i", $student_id);
    $student_query->execute();
    $student_result = $student_query->get_result();
    if ($student_result && $student_result->num_rows > 0) {
        $student_data = $student_result->fetch_assoc();
        $student_name = htmlspecialchars($student_data['firstname'] . ' ' . $student_data['lastname']);
        $student_email = htmlspecialchars($student_data['email'] ?? 'N/A');
    }
    $student_query->close();
}

if ($result->num_rows > 0) {
    // Already marked: update to Present
    $update = $conn->prepare("UPDATE attendance SET status = 'Present' WHERE student_id = ? AND date = ?");
    $update->bind_param("is", $student_id, $date);
    if ($update->execute()) {
        $attendance_marked = true;
        $message = "✅ Attendance updated to Present!";
        $message_type = "success";
    }
    $update->close();
} else {
    // Insert new attendance as Present
    $insert = $conn->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, 'Present')");
    $insert->bind_param("is", $student_id, $date);
    if ($insert->execute()) {
        $attendance_marked = true;
        $message = "✅ Attendance marked as Present!";
        $message_type = "success";
    }
    $insert->close();
}
$check->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance - Student Portal</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --info: #4895ef;
            --warning: #f72585;
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
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Attendance Card */
        .attendance-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.8s ease-in-out;
            max-width: 500px;
            width: 90%;
            margin: 2rem auto;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Success Animation */
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .success-pulse {
            animation: successPulse 2s ease-in-out infinite;
        }

        /* Student Info Card */
        .student-info-card {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .student-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
        }

        /* Status Badge */
        .status-badge {
            font-size: 1.1rem;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-action {
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        /* QR Code Display */
        .qr-display {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            margin: 1.5rem 0;
            border: 2px dashed #dee2e6;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .attendance-card {
                padding: 2rem 1.5rem;
                margin: 1rem;
                width: 95%;
            }

            .student-avatar {
                width: 70px;
                height: 70px;
                font-size: 1.75rem;
            }

            .student-info-card {
                padding: 1.25rem;
            }

            .btn-action {
                padding: 0.875rem 1.25rem;
                font-size: 0.95rem;
            }

            h2 {
                font-size: 1.5rem !important;
            }

            .status-badge {
                font-size: 1rem;
                padding: 0.6rem 1.25rem;
            }
        }

        @media (max-width: 576px) {
            .attendance-card {
                padding: 1.5rem 1rem;
            }

            .student-avatar {
                width: 60px;
                height: 60px;
                font-size: 1.5rem;
            }

            .btn-group {
                flex-direction: column;
                gap: 0.75rem;
            }

            .btn-group .btn {
                width: 100%;
            }

            h3 {
                font-size: 1.25rem !important;
            }

            .qr-display {
                padding: 1rem;
            }
        }

        @media (max-width: 400px) {
            .attendance-card {
                padding: 1.25rem 0.75rem;
            }

            .student-info-card {
                padding: 1rem;
            }

            .student-avatar {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            h2 {
                font-size: 1.35rem !important;
            }
        }

        /* Loading Animation */
        .loading-spinner {
            width: 3rem;
            height: 3rem;
        }

        /* Print Styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            .attendance-card {
                box-shadow: none;
                border: 2px solid #000;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .attendance-card {
                background: rgba(30, 30, 30, 0.95);
                color: #fff;
            }
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="background-animation"></div>

    <!-- Main Content -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-10 col-lg-8">
                <div class="attendance-card">
                    <!-- Header -->
                    <div class="text-center mb-4">
                        <div class="student-avatar">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <h2 class="fw-bold text-dark mb-2">Attendance Recorded</h2>
                        <p class="text-muted">QR Code Scan Result</p>
                    </div>

                    <!-- Student Information -->
                    <div class="student-info-card">
                        <h3 class="h4 fw-bold mb-2"><?= $student_name ?></h3>
                        <p class="mb-1 opacity-75">
                            <i class="fas fa-envelope me-2"></i><?= $student_email ?>
                        </p>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-calendar me-2"></i><?= date('F j, Y') ?>
                        </p>
                    </div>

                    <!-- Attendance Status -->
                    <div class="text-center mb-4">
                        <?php if ($attendance_marked): ?>
                            <div class="success-pulse">
                                <span class="status-badge bg-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    PRESENT - Attendance Recorded
                                </span>
                            </div>
                            <div class="mt-3">
                                <p class="text-success mb-0">
                                    <i class="fas fa-clock me-2"></i>
                                    Time: <?= date('h:i A') ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <span class="status-badge bg-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                ATTENDANCE FAILED
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Message Display -->
                    <?php if (isset($message)): ?>
                        <div class="alert alert-<?= $message_type ?> text-center" role="alert">
                            <i class="fas fa-<?= $message_type === 'success' ? 'check' : 'exclamation' ?>-circle me-2"></i>
                            <?= $message ?>
                        </div>
                    <?php endif; ?>

                    <!-- Student Details -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-info-circle me-2"></i>
                                Student Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Student ID:</strong>
                                </div>
                                <div class="col-6">
                                    <?= $student_id ?>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Date:</strong>
                                </div>
                                <div class="col-6">
                                    <?= date('M j, Y') ?>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <strong>Status:</strong>
                                </div>
                                <div class="col-6">
                                    <span class="badge bg-success">Present</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="btn-group w-100 no-print" role="group">
                        <a href="dashboard.php" class="btn btn-primary btn-action">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Back to Dashboard
                        </a>
                        <button onclick="window.print()" class="btn btn-outline-primary btn-action">
                            <i class="fas fa-print me-2"></i>
                            Print Receipt
                        </button>
                    </div>

                    <!-- Additional Info -->
                    <div class="mt-4 text-center">
                        <p class="text-muted small mb-0">
                            <i class="fas fa-shield-alt me-1"></i>
                            This attendance record has been securely saved to the database.
                        </p>
                    </div>

                    <!-- Auto-redirect notice -->
                    <?php if ($attendance_marked): ?>
                        <div class="mt-3 text-center">
                            <p class="text-info small mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Redirecting to dashboard in <span id="countdown">10</span> seconds...
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Auto-redirect after successful attendance marking
        <?php if ($attendance_marked): ?>
        let countdown = 10;
        const countdownElement = document.getElementById('countdown');
        
        const countdownInterval = setInterval(() => {
            countdown--;
            if (countdownElement) {
                countdownElement.textContent = countdown;
            }
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                window.location.href = 'dashboard.php';
            }
        }, 1000);

        // Allow manual override of redirect
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                clearInterval(countdownInterval);
                if (countdownElement) {
                    countdownElement.textContent = 'Redirect cancelled';
                }
            }
        });
        <?php endif; ?>

        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add pulse animation to success message
            const successBadge = document.querySelector('.status-badge.bg-success');
            if (successBadge) {
                setTimeout(() => {
                    successBadge.classList.add('success-pulse');
                }, 500);
            }

            // Add click effect to buttons
            const buttons = document.querySelectorAll('.btn-action');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    this.style.transform = 'scale(0.98)';
                    setTimeout(() => {
                        this.style.transform = '';
                    }, 150);
                });
            });

            // Add page load animation
            const cards = document.querySelectorAll('.card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>