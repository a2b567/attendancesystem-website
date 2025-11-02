<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/phpqrcode/qrlib.php'; // ✅ safer absolute path

// ✅ Only admin can access
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);
    if ($student_id <= 0) {
        die("❌ Invalid student ID.");
    }

    // Fetch student details
    $stmt = $conn->prepare("SELECT firstname, lastname, email FROM students WHERE id = ?");
    if (!$stmt) {
        die("❌ Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) {
        die("❌ Failed to fetch student record: " . $conn->error);
    }

    $student = $result->fetch_assoc();
    $stmt->close();

    if ($student) {
        // ✅ Generate unique QR content
        $qrData = "STUDENT_ID={$student_id}|EMAIL={$student['email']}";

        // ✅ Create folder if not exists
        $dir = __DIR__ . "/qrcodes/";
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                die("❌ Failed to create QR code directory.");
            }
        }

        // ✅ Generate file path
        $filePath = $dir . "student_" . $student_id . ".png";

        // ✅ Generate QR code
        QRcode::png($qrData, $filePath, QR_ECLEVEL_L, 6);

        // ✅ Save only relative path to DB
        $relativePath = "qrcodes/student_" . $student_id . ".png";
        $update = $conn->prepare("UPDATE students SET qr_code = ? WHERE id = ?");
        if (!$update) {
            die("❌ Prepare failed: " . $conn->error);
        }

        $update->bind_param("si", $relativePath, $student_id);
        if (!$update->execute()) {
            die("❌ Failed to save QR code path in DB: " . $update->error);
        }
        $update->close();

        header("Location: admin_students.php?msg=QR generated successfully!");
        exit();
    } else {
        die("❌ Student not found.");
    }
} else {
    die("❌ Invalid request.");
}
