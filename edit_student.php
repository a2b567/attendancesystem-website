<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

$id = intval($_GET['id'] ?? 0);
if(!$id) { header("Location: dashboard.php"); exit(); }

// Fetch student
$stmt = $conn->prepare("SELECT * FROM students WHERE id=?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $stmt = $conn->prepare("UPDATE students SET firstname=?, lastname=?, email=? WHERE id=?");
    $stmt->bind_param("sssi", $firstname, $lastname, $email, $id);
    $stmt->execute();
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Student</title>
<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f4f6f8;
    margin: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
}

.center-card {
    background: #fff;
    padding: 30px 40px;
    border-radius: 12px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    width: 400px;
    max-width: 90%;
    text-align: center;
}

.center-card h2 {
    margin-bottom: 20px;
    color: #333;
}

.center-card form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.center-card input[type="text"],
.center-card input[type="email"] {
    padding: 10px 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
    font-size: 15px;
}

.center-card button {
    padding: 10px 15px;
    border-radius: 8px;
    border: none;
    background: #00a8ff;
    color: #fff;
    font-size: 16px;
    cursor: pointer;
    transition: 0.3s;
}

.center-card button:hover {
    background: #0097e6;
}

.back-btn {
    display: inline-block;
    margin-bottom: 20px;
    text-decoration: none;
    background: #007bff;
    color: #fff;
    padding: 8px 14px;
    border-radius: 6px;
    transition: 0.3s;
}

.back-btn:hover {
    background: #0056b3;
}
</style>
</head>
<body>

<div class="center-card">
    <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
    <h2>Edit Student</h2>
    <form method="post">
        <input type="text" name="firstname" placeholder="First Name" value="<?= htmlspecialchars($student['firstname']) ?>" required>
        <input type="text" name="lastname" placeholder="Last Name" value="<?= htmlspecialchars($student['lastname']) ?>" required>
        <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($student['email']) ?>" required>
        <button type="submit">Update Student</button>
    </form>
</div>

</body>
</html>
