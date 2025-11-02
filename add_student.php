<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();
require_once __DIR__ . '/phpqrcode/qrlib.php';

$error = '';
$successMsg = '';
$qrFile = '';
$photoDB = '';
$sn = $fn = $ln = $email = '';

// ✅ QR CODE DOWNLOAD HANDLER
if (isset($_GET['download_qr']) && isset($_GET['student_number'])) {
    $studentNumber = trim($_GET['student_number']);
    $qrPath = __DIR__ . '/qrcodes/' . $studentNumber . '.png';
    
    if (file_exists($qrPath)) {
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . $studentNumber . '_qrcode.png"');
        header('Content-Length: ' . filesize($qrPath));
        readfile($qrPath);
        exit;
    } else {
        header('Location: add_student.php?error=QR+code+not+found');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sn = trim($_POST['student_number'] ?? '');
    $fn = trim($_POST['firstname'] ?? '');
    $ln = trim($_POST['lastname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $photo = $_FILES['photo'] ?? null;

    if ($sn === '' || $fn === '' || $ln === '' || $email === '') {
        $error = "⚠️ All fields are required!";
    } else {
        // Check for duplicate student number or email
        $duplicate = false;
        $check = $conn->prepare("SELECT id FROM students WHERE student_number=? OR email=?");
        if ($check) {
            $check->bind_param("ss", $sn, $email);
            $check->execute();
            $check->store_result();
            if ($check->num_rows > 0) $duplicate = true;
            $check->close();
        }

        if ($duplicate) {
            $error = "⚠️ Student number or email already exists!";
        } else {
            // ✅ Create upload folder if not exists
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);

            // ✅ Optional: Save uploaded photo if provided
            if ($photo && $photo['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($photo['name'], PATHINFO_EXTENSION);
                $photoName = $sn . '_' . time() . ($ext ? '.' . $ext : '');
                $photoPath = $uploadDir . $photoName;
                move_uploaded_file($photo['tmp_name'], $photoPath);
                $photoDB = 'uploads/' . $photoName;
            }

            // ✅ Insert into database (no photo column)
            $insert = $conn->prepare("INSERT INTO students (student_number, firstname, lastname, email) VALUES (?, ?, ?, ?)");
            if ($insert) {
                $insert->bind_param("ssss", $sn, $fn, $ln, $email);
                if ($insert->execute()) {
                    // ✅ Create QR folder if not exists
                    $qrFolder = __DIR__ . '/qrcodes/';
                    if (!is_dir($qrFolder)) mkdir($qrFolder, 0777, true);

                    // Generate QR code
                    $qrFile = $qrFolder . '/' . $sn . '.png';
                    $qrData = $sn . '|' . $fn . '|' . $ln . '|' . $email;
                    QRcode::png($qrData, $qrFile, QR_ECLEVEL_L, 4);

                    $successMsg = "✅ Student added successfully!";
                } else {
                    $error = "❌ Failed to add student: " . htmlspecialchars($insert->error);
                }
                $insert->close();
            } else {
                $error = "❌ Failed to prepare insert statement: " . htmlspecialchars($conn->error);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Student - Student Portal</title>
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

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
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

        /* Main Card */
        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 0.8s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
            text-align: center;
        }

        /* Form Styling */
        .form-floating > .form-control {
            border-radius: 12px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(67, 97, 238, 0.25);
        }

        /* File Upload Styling */
        .file-upload-container {
            border: 2px dashed #dee2e6;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-upload-container:hover {
            border-color: var(--primary);
            background: #f1f3ff;
        }

        .file-upload-container.dragover {
            border-color: var(--primary);
            background: #e8edff;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 12px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4);
        }

        .btn-outline-primary {
            border-radius: 12px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), var(--info));
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(76, 201, 240, 0.4);
        }

        /* Preview Images */
        .preview-container {
            text-align: center;
            margin: 1.5rem 0;
        }

        .preview-image {
            width: 200px;
            height: 200px;
            border-radius: 12px;
            object-fit: cover;
            border: 3px solid #e9ecef;
            transition: all 0.3s ease;
        }

        .preview-image:hover {
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .qr-code {
            width: 180px;
            height: 180px;
            border-radius: 12px;
            border: 3px solid #e9ecef;
            padding: 10px;
            background: white;
        }

        /* Success Animation */
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .success-pulse {
            animation: successPulse 2s ease-in-out infinite;
        }

        /* Download Button Animation */
        @keyframes downloadBounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }

        .download-bounce {
            animation: downloadBounce 2s infinite;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-card {
                margin: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .page-header h1 {
                font-size: 1.75rem;
            }

            .file-upload-container {
                padding: 1.5rem;
            }

            .preview-image {
                width: 150px;
                height: 150px;
            }

            .qr-code {
                width: 140px;
                height: 140px;
            }

            .btn-primary, .btn-outline-primary, .btn-success {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px 0;
            }

            .main-card {
                margin: 0.5rem;
            }

            .page-header {
                padding: 1.25rem;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .file-upload-container {
                padding: 1rem;
            }

            .preview-image {
                width: 120px;
                height: 120px;
            }

            .qr-code {
                width: 110px;
                height: 110px;
            }

            .form-floating > .form-control {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }

        @media (max-width: 400px) {
            .page-header {
                padding: 1rem;
            }

            .page-header h1 {
                font-size: 1.35rem;
            }

            .preview-image {
                width: 100px;
                height: 100px;
            }

            .qr-code {
                width: 90px;
                height: 90px;
            }
        }

        /* Loading State */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }

        /* Custom file input */
        .custom-file-input {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }

        .file-upload-label {
            cursor: pointer;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            transition: all 0.3s ease;
        }

        .file-upload-label:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="background-animation"></div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-lg-8 col-xl-6">
                <div class="main-card">
                    <!-- Header -->
                    <div class="page-header">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <i class="fas fa-user-graduate fa-2x me-3"></i>
                            <h1 class="h2 mb-0">Add New Student</h1>
                        </div>
                        <p class="mb-0 opacity-75">Fill in the student details below</p>
                    </div>

                    <!-- Back Button -->
                    <div class="p-4 pb-0">
                        <a href="dashboard.php" class="btn btn-outline-primary mb-4">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Dashboard
                        </a>
                    </div>

                    <!-- Messages -->
                    <?php if ($error): ?>
                        <div class="alert alert-danger mx-4 mt-2" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMsg): ?>
                        <div class="alert alert-success mx-4 mt-2" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($successMsg) ?>
                        </div>
                    <?php endif; ?>

                    <!-- Main Form -->
                    <div class="p-4 pt-0">
                        <form method="post" enctype="multipart/form-data" id="studentForm">
                            <div class="row g-3">
                                <!-- Student Number -->
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="student_number" 
                                               name="student_number" placeholder=" " required 
                                               value="<?= htmlspecialchars($sn) ?>">
                                        <label for="student_number">
                                            <i class="fas fa-id-card me-2"></i>Student Number
                                        </label>
                                    </div>
                                </div>

                                <!-- First Name -->
                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="firstname" 
                                               name="firstname" placeholder=" " required 
                                               value="<?= htmlspecialchars($fn) ?>">
                                        <label for="firstname">
                                            <i class="fas fa-user me-2"></i>First Name
                                        </label>
                                    </div>
                                </div>

                                <!-- Last Name -->
                                <div class="col-12 col-md-6">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="lastname" 
                                               name="lastname" placeholder=" " required 
                                               value="<?= htmlspecialchars($ln) ?>">
                                        <label for="lastname">
                                            <i class="fas fa-user me-2"></i>Last Name
                                        </label>
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="col-12">
                                    <div class="form-floating">
                                        <input type="email" class="form-control" id="email" 
                                               name="email" placeholder=" " required 
                                               value="<?= htmlspecialchars($email) ?>">
                                        <label for="email">
                                            <i class="fas fa-envelope me-2"></i>Email Address
                                        </label>
                                    </div>
                                </div>

                                <!-- Photo Upload -->
                                <div class="col-12">
                                    <div class="file-upload-container" id="fileUploadContainer">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-primary mb-3"></i>
                                        <h5 class="mb-2">Upload Student Photo</h5>
                                        <p class="text-muted mb-3">Drag & drop or click to browse</p>
                                        <input type="file" class="custom-file-input" id="photo" 
                                               name="photo" accept="image/*">
                                        <label for="photo" class="file-upload-label">
                                            <i class="fas fa-camera me-2"></i>Choose Photo
                                        </label>
                                        <div class="mt-2">
                                            <small class="text-muted">Supported formats: JPG, PNG, GIF (Max: 5MB)</small>
                                        </div>
                                    </div>
                                    <div id="filePreview" class="mt-3 text-center"></div>
                                </div>

                                <!-- Submit Button -->
                                <div class="col-12">
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-user-plus me-2"></i>
                                            Add Student
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <!-- Success Preview Section -->
                        <?php if ($successMsg): ?>
                            <div class="row mt-5">
                                <?php if ($photoDB): ?>
                                    <div class="col-12 col-md-6 mb-4">
                                        <div class="preview-container">
                                            <h5 class="mb-3">
                                                <i class="fas fa-image me-2 text-primary"></i>
                                                Student Photo
                                            </h5>
                                            <img src="<?= htmlspecialchars($photoDB) ?>" 
                                                 alt="Student Photo" class="preview-image">
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-12 <?= $photoDB ? 'col-md-6' : 'col-12' ?>">
                                    <div class="preview-container">
                                        <h5 class="mb-3">
                                            <i class="fas fa-qrcode me-2 text-success"></i>
                                            Generated QR Code
                                        </h5>
                                        <img src="<?= 'qrcodes/' . htmlspecialchars($sn) . '.png' ?>" 
                                             alt="QR Code" class="qr-code success-pulse">
                                        <p class="mt-2 text-muted small">
                                            Scan this QR code for attendance
                                        </p>
                                        <!-- ✅ DOWNLOAD QR CODE BUTTON -->
                                        <div class="mt-3">
                                            <a href="?download_qr=1&student_number=<?= htmlspecialchars($sn) ?>" 
                                               class="btn btn-success download-bounce">
                                                <i class="fas fa-download me-2"></i>
                                                Download QR Code
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('photo');
            const fileUploadContainer = document.getElementById('fileUploadContainer');
            const filePreview = document.getElementById('filePreview');
            const form = document.getElementById('studentForm');

            // File upload drag and drop
            fileUploadContainer.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            fileUploadContainer.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            fileUploadContainer.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                if (e.dataTransfer.files.length) {
                    fileInput.files = e.dataTransfer.files;
                    handleFileSelect(e.dataTransfer.files[0]);
                }
            });

            // File input change
            fileInput.addEventListener('change', function(e) {
                if (this.files.length) {
                    handleFileSelect(this.files[0]);
                }
            });

            function handleFileSelect(file) {
                if (file) {
                    if (file.size > 5 * 1024 * 1024) {
                        alert('File size must be less than 5MB');
                        fileInput.value = '';
                        return;
                    }

                    if (!file.type.startsWith('image/')) {
                        alert('Please select an image file');
                        fileInput.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        filePreview.innerHTML = `
                            <div class="alert alert-info">
                                <i class="fas fa-check-circle me-2"></i>
                                File selected: ${file.name}
                                <div class="mt-2">
                                    <img src="${e.target.result}" class="preview-image" style="width: 100px; height: 100px;">
                                </div>
                            </div>
                        `;
                    };
                    reader.readAsDataURL(file);
                }
            }

            // Form submission loading state
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding Student...';
                submitBtn.disabled = true;
                this.classList.add('loading');
            });

            // Input validation
            const inputs = form.querySelectorAll('input[required]');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    if (!this.value) {
                        this.classList.add('is-invalid');
                    } else {
                        this.classList.remove('is-invalid');
                    }
                });
            });

            // Auto-focus first empty field
            const firstEmptyField = Array.from(inputs).find(input => !input.value);
            if (firstEmptyField) {
                firstEmptyField.focus();
            }
        });
    </script>
</body>
</html>