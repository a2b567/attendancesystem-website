<?php
session_start();
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ Invalid email format.";
    } else {
        $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($user = $res->fetch_assoc()) {
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];

                if (!empty($_POST['remember'])) {
                    setcookie("user_id", $user['id'], time() + (86400 * 30), "/");
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "❌ Incorrect password.";
            }
        } else {
            $error = "⚠️ No account found with that email.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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

        /* Login card styling */
        .login-card {
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
        .btn-login {
            background: linear-gradient(90deg, #ffd700, #ff8c00);
            border: none;
            color: #fff;
            font-weight: 600;
            padding: 1rem;
            border-radius: 15px;
            transition: 0.4s;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #ff8c00, #ffd700);
            transform: translateY(-2px);
            color: #fff;
        }

        /* Checkbox styling */
        .form-check-input:checked {
            background-color: #ffd700;
            border-color: #ffd700;
        }

        .form-check-label {
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

        /* Error message */
        .alert-error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.3);
            color: #ff6b6b;
            animation: shake 0.3s;
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
            .login-card {
                margin: 1rem;
                padding: 2rem 1.5rem !important;
            }
            
            .btn-login {
                padding: 0.875rem;
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

    <!-- Login Card -->
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="login-card p-4 p-md-5">
                    <h2 class="text-center text-white mb-4 fw-bold">Login</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error text-center mb-4">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post">
                        <div class="form-floating position-relative">
                            <input type="email" class="form-control" id="email" name="email" placeholder=" " required>
                            <label for="email">Email</label>
                        </div>
                        
                        <div class="form-floating position-relative">
                            <input type="password" class="form-control" id="password" name="password" placeholder=" " required>
                            <label for="password">Password</label>
                            <span class="password-toggle" onclick="togglePassword()">
                                <i class="bi bi-eye"></i>
                            </span>
                        </div>
                        
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">
                                Remember Me
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-login w-100 mb-3">Login</button>
                        
                        <p class="text-center text-white mb-0">
                            Don't have an account? <a href="register.php">Register</a>
                        </p>
                    </form>
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
    </script>
</body>
</html>