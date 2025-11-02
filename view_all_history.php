<?php
require_once 'config.php';

// ✅ Fetch attendance records with student info
$sql = "
    SELECT s.student_number, s.firstname, s.lastname, a.date, a.status
    FROM attendance a
    JOIN students s ON a.student_number = s.student_number
    ORDER BY a.date DESC, s.firstname ASC
";
$result = $conn->query($sql);

// ✅ Group by date
$attendance_by_date = [];
while ($row = $result->fetch_assoc()) {
    $attendance_by_date[$row['date']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Attendance History</title>
<style>
:root {
  --primary: #007bff;
  --primary-dark: #0056b3;
  --bg: #f4f7fc;
  --text-dark: #2c3e50;
  --text-light: #7f8c8d;
}

body {
  font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  margin: 0;
  background: var(--bg);
}

/* Header */
.header {
  background: var(--primary);
  color: white;
  padding: 20px 40px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.header h2 { margin: 0; font-weight: 600; font-size: 20px; letter-spacing: 0.5px; }
.btn {
  border: none;
  border-radius: 6px;
  padding: 10px 18px;
  cursor: pointer;
  font-weight: 600;
  transition: 0.3s;
}
.back-btn {
  background: white;
  color: var(--primary);
}
.back-btn:hover {
  background: var(--primary-dark);
  color: white;
}
.export-btn {
  background: #28a745;
  color: white;
}
.export-btn:hover {
  background: #218838;
}


/* Container */
.container {
  max-width: 1100px;
  margin: 30px auto;
  padding: 0 20px 50px;
 max-height: 350px; /* Adjust height as needed */
 overflow-y: auto;
 padding-right: 10px; /* Optional: for scrollbar spacing */
 margin-bottom: 20px; /* Optional spacing */
}
/* Date Sections */
.date-section {
  background: white;
  margin-bottom: 35px;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 6px 20px rgba(0,0,0,0.06);
  transition: 0.3s;
}
.date-section:hover {
  transform: translateY(-3px);
}
.date-header {
  background: var(--primary);
  color: white;
  padding: 15px 20px;
  font-size: 18px;
  font-weight: 600;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.date-header small {
  font-weight: normal;
  color: #dff1ff;
}

/* Table */
table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 14px;
  text-align: left;
  border-bottom: 1px solid #f0f0f0;
}
th {
  background: #f9fbfd;
  color: var(--text-dark);
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
td {
  font-size: 15px;
  color: #333;
}
tr:hover td {
  background: #f9f9ff;
}
.status-present {
  color: #27ae60;
  font-weight: 600;
}
.status-absent {
  color: #e74c3c;
  font-weight: 600;
}
.no-record {
  text-align: center;
  color: var(--text-light);
  font-style: italic;
  margin-top: 30px;
}

/* Responsive */
@media (max-width: 768px) {
  .header { flex-direction: column; text-align: center; }
  table, th, td { font-size: 13px; }
}
</style>
</head>
<body>

<div class="header">
  <h2>📅 Attendance History</h2>
  <div>
    <button class="btn export-btn" onclick="window.location.href='export_excel.php'">⬇ Download Excel</button>
    <button class="btn back-btn" onclick="window.location.href='dashboard.php'">⬅ Back</button>
  </div>
</div>
<div class="container">
  <?php if (!empty($attendance_by_date)): ?>
    <?php foreach ($attendance_by_date as $date => $records): ?>
      <div class="date-section">
        <div class="date-header">
          <span><?= htmlspecialchars(date("F d, Y", strtotime($date))) ?></span>
          <small><?= count($records) ?> record(s)</small>
        </div>
        <table class="attendance-table">
          <thead>
            <tr>
              <th>Student Number</th>
              <th>Full Name</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['student_number']) ?></td>
                <td><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></td>
                <td class="<?= $row['status'] === 'Present' ? 'status-present' : 'status-absent' ?>">
                  <?= htmlspecialchars($row['status']) ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endforeach; ?>
  <?php else: ?>
    <p class="no-record">No attendance records found.</p>
  <?php endif; ?>
</div>
</body>
</html>
