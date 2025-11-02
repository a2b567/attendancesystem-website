<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function require_login(): void {
    if (!is_logged_in()) {
        session_unset();
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

function logout(): void {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

function current_user_name(): string {
    return $_SESSION['name'] ?? 'User';
}
?>
