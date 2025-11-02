<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    if($id){
        // Delete QR file if exists
        $stmt = $conn->prepare("SELECT student_number FROM students WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        if($res && file_exists('qrcodes/'.$res['student_number'].'.png')){
            unlink('qrcodes/'.$res['student_number'].'.png');
        }
        $stmt = $conn->prepare("DELETE FROM students WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
    }
}
header("Location: dashboard.php");
exit();
