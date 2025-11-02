<?php 
require_once 'config.php';

// Handle attendance form submission
if (isset($_POST['toggle'])) {
    $student_id = intval($_POST['student_id']);
    $current_status = $_POST['status'] ?? 'Absent';
    $new_status = $current_status === 'Present' ? 'Absent' : 'Present';
    $date = date('Y-m-d');

    // Check if already marked
    $check = $conn->prepare("SELECT id FROM attendance WHERE student_id=? AND date=?");
    $check->bind_param("is", $student_id, $date);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows > 0) {
        $update = $conn->prepare("UPDATE attendance SET status=? WHERE student_id=? AND date=?");
        $update->bind_param("sis", $new_status, $student_id, $date);
        $update->execute();
        $update->close();
    } else {
        $insert = $conn->prepare("INSERT INTO attendance (student_id, date, status) VALUES (?, ?, ?)");
        $insert->bind_param("iss", $student_id, $date, $new_status);
        $insert->execute();
        $insert->close();
    }

    header("Location: mark_attendance.php");
    exit();
}

// Fetch students with today's attendance
$students = $conn->query("
    SELECT s.id, s.firstname, s.lastname, a.status
    FROM students s
    LEFT JOIN attendance a 
        ON s.id = a.student_id AND a.date = CURDATE()
    ORDER BY s.firstname ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Student Attendance</title>
<link rel="stylesheet" href="assets/styles.css">
<style>
.center-card { max-width: 900px; margin: 40px auto; background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 0 10px rgba(0,0,0,0.1);}
.students-table { width: 100%; border-collapse: collapse;}
.students-table th, .students-table td { padding: 10px; border-bottom: 1px solid #ddd; text-align: center;}
.inline-form { display: inline-block;}
button { padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; color: #fff;}
button.present { background: green;}
button.absent { background: red;}
button:hover { opacity: 0.8;}
nav { background: #333; color: #fff; padding: 10px; text-align: center;}
nav a { color: #fff; text-decoration: none; margin-left: 15px;}
</style>
</head>
<body>

<nav>
  <h2>📋 Student Attendance</h2>
  <a href="dashboard.php">⬅ Back to Dashboard</a>
</nav>

<div class="center-card">
  <table class="students-table">
    <tr>
      <th>ID</th>
      <th>Name</th>
      <th>Status</th>
    </tr>
    <?php while($row = $students->fetch_assoc()): 
        $status = $row['status'] ?? 'Absent';
    ?>
    <tr>
      <td><?= $row['id'] ?></td>
      <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
      <td>
        <form method="post" class="inline-form">
          <input type="hidden" name="student_id" value="<?= $row['id'] ?>">
          <input type="hidden" name="status" value="<?= $status ?>">
          <button type="submit" name="toggle" class="<?= strtolower($status) ?>"><?= $status ?></button>
        </form>
      </td>
    </tr>
    <?php endwhile; ?>
  </table>
</div>

</body>
</html>
