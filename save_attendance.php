<?php
session_start();
require_once 'config.php';

if (!isset($_POST['qrData'])) {
    exit("❌ No QR data received");
}

$qrData = $_POST['qrData'];

// Extract student ID
if (preg_match('/STUDENT_ID_(\d+)_/', $qrData, $matches)) {
    $student_id = intval($matches[1]);
    $date = date('Y-m-d');

    // Check if already marked
    $stmt = $conn->prepare("SELECT id FROM attendance WHERE student_id = ? AND date = ?");
    $stmt->bind_param("is", $student_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "✅ Attendance already recorded for today.";
    } else {
        // Record attendance
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, 'Present')");
        $stmt->bind_param("is", $student_id, $date);
        if ($stmt->execute()) {
            echo "🎉 Attendance recorded successfully!";
        } else {
            echo "❌ Database error: " . $conn->error;
        }
    }
} else {
    echo "❌ Invalid QR code.";
}
?>
