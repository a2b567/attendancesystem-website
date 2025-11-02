<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);
    $role = 'user';

    if (strlen($password) < 6) {
        $error = "⚠️ Password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "⚠️ Email already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
            $insert->bind_param("sss", $email, $hashed, $role);

            if ($insert->execute()) {
                $success = "✅ Account created! You can now log in.";
            } else {
                $error = "❌ Error creating account: " . $conn->error;
            }
            $insert->close();
        }
        $check->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom styles to complement Bootstrap */
        body, html {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Video as background */
        #bg-video {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -2;
        }

        /* Gradient overlay */
        .video-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.4), rgba(0, 0, 0, 0.7));
            z-index: -1;
        }

        /* Register card styling */
        .register-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(15px);
            border-radius: 25px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.3);
            animation: fadeIn 1s ease-in-out;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Form styling */
        .form-floating {
            margin-bottom: 1.5rem;
        }

        .form-floating > .form-control {
            background: rgba(255, 255, 255, 0.25);
            border: none;
            border-radius: 15px;
            color: #fff;
            padding: 1rem 1rem;
        }

        .form-floating > .form-control:focus {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
            color: #fff;
        }

        .form-floating > label {
            color: #fff;
            padding: 1rem 1rem;
        }

        .form-floating > .form-control:focus ~ label,
        .form-floating > .form-control:not(:placeholder-shown) ~ label {
            color: #ffd700;
            background: rgba(0, 0, 0, 0.2);
            padding: 0.5rem 0.75rem;
            border-radius: 5px;
            transform: scale(0.85) translateY(-0.9rem) translateX(0.15rem);
        }

        /* Password toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 10;
            color: #fff;
        }

        /* Button styling */
        .btn-register {
            background: linear-gradient(90deg, #ffd700, #ff8c00);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 1rem;
            border-radius: 15px;
            transition: 0.4s;
            font-size: 1.1rem;
        }

        .btn-register:hover {
            background: linear-gradient(90deg, #ff8c00, #ffd700);
            transform: translateY(-2px);
            color: #fff;
        }

        /* Links */
        a {
            color: #ffd700;
            font-weight: 500;
            text-decoration: none;
        }

        a:hover {
            color: #ff8c00;
            text-decoration: underline;
        }

        /* Error and Success messages */
        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
            animation: shake 0.3s;
        }

        .alert-success {
            background: rgba(144, 238, 144, 0.2);
            border: 1px solid rgba(144, 238, 144, 0.3);
            color: #90ee90;
            animation: fadeIn 0.5s;
        }

        @keyframes shake {
            0% { transform: translateX(0px); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
            100% { transform: translateX(0); }
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .register-card {
                margin: 1rem;
                padding: 2rem 1.5rem !important;
            }
            
            .btn-register {
                padding: 0.875rem;
                font-size: 1rem;
            }
            
            .form-floating > .form-control {
                padding: 0.875rem 0.875rem;
            }
        }

        /* Extra small devices */
        @media (max-width: 400px) {
            .register-card {
                padding: 1.5rem 1rem !important;
            }
            
            h2 {
                font-size: 1.5rem !important;
            }
        }

        /* Ensure proper contrast on mobile */
        @media (max-width: 768px) {
            .form-floating > .form-control {
                font-size: 16px; /* Prevent zoom on iOS */
            }
        }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center">

    <!-- Background Video -->
    <video id="bg-video" autoplay muted loop>
        <source src="aurora.mp4" type="video/mp4">
        Your browser does not support HTML5 video.
    </video>
    
    <!-- Gradient Overlay -->
    <div class="video-overlay"></div>

    <!-- Register Card -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="register-card p-4 p-md-5">
                    <h2 class="text-center text-white mb-4 fw-bold">Create Account</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error text-center mb-4">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success text-center mb-4">
                            <i class="fas fa-check-circle me-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="form-floating position-relative">
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder=" " required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <label for="email">
                                <i class="fas fa-envelope me-2"></i>Email
                            </label>
                        </div>
                        
                        <div class="form-floating position-relative">
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder=" " required>
                            <label for="password">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-register">
                                <i class="fas fa-user-plus me-2"></i>Register
                            </button>
                        </div>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-white mb-0">
                            Already have an account? 
                            <a href="index.php" class="fw-bold">Login here</a>
                        </p>
                    </div>

                    <!-- Password requirements hint -->
                    <div class="mt-3">
                        <p class="text-white-50 small mb-0">
                            <i class="fas fa-info-circle me-1"></i>
                            Password must be at least 6 characters long
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Add input validation styling
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            
            passwordInput.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 6) {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                }
            });

            // Prevent form submission if password is too short
            document.querySelector('form').addEventListener('submit', function(e) {
                if (passwordInput.value.length > 0 && passwordInput.value.length < 6) {
                    e.preventDefault();
                    passwordInput.focus();
                }
            });
        });
    </script>
</body>
</html>