<?php
session_start();
require_once 'config.php';
require_once 'helpers.php';
require_login();

// Get all tables
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_array()) {
    $tables[] = $row[0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Viewer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .table-container { background: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; }
        .table-name { color: #4361ee; font-weight: bold; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">📊 Database Viewer</h1>
        <a href="dashboard.php" class="btn btn-primary mb-4">← Back to Dashboard</a>

        <?php foreach ($tables as $table): ?>
            <div class="table-container">
                <h3 class="table-name"><?= htmlspecialchars($table) ?></h3>
                
                <?php
                // Get table structure
                $structure_result = $conn->query("DESCRIBE $table");
                echo "<h5>Table Structure:</h5>";
                echo "<table class='table table-sm table-bordered'>";
                echo "<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>";
                echo "<tbody>";
                while ($col = $structure_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>{$col['Field']}</td>";
                    echo "<td>{$col['Type']}</td>";
                    echo "<td>{$col['Null']}</td>";
                    echo "<td>{$col['Key']}</td>";
                    echo "<td>{$col['Default']}</td>";
                    echo "<td>{$col['Extra']}</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";

                // Get table data
                $data_result = $conn->query("SELECT * FROM $table LIMIT 100");
                $row_count = $data_result->num_rows;
                
                echo "<h5>Data (showing first $row_count records):</h5>";
                if ($row_count > 0) {
                    echo "<div class='table-responsive'>";
                    echo "<table class='table table-striped table-hover'>";
                    
                    // Table headers
                    echo "<thead><tr>";
                    while ($field = $data_result->fetch_field()) {
                        echo "<th>{$field->name}</th>";
                    }
                    echo "</tr></thead>";
                    
                    // Table data
                    echo "<tbody>";
                    $data_result->data_seek(0); // Reset pointer
                    while ($row = $data_result->fetch_assoc()) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                        }
                        echo "</tr>";
                    }
                    echo "</tbody></table></div>";
                } else {
                    echo "<p class='text-muted'>No data found in this table.</p>";
                }
                ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>