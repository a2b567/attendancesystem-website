<?php
require_once 'config.php';
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=attendance_records.xls");

echo "Student Number\tName\tDate\tStatus\n";

$sql = "
    SELECT s.student_number, s.firstname, s.lastname, a.date, a.status
    FROM attendance a
    JOIN students s ON a.student_number = s.student_number
    ORDER BY a.date DESC
";
$result = $conn->query($sql);
while ($row = $result->fetch_assoc()) {
    echo "{$row['student_number']}\t{$row['firstname']} {$row['lastname']}\t{$row['date']}\t{$row['status']}\n";
}
exit;
?>

