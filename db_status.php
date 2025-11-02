<?php
require_once 'config.php';

echo "<h2>Database Status</h2>";
echo "Host: " . $host . "<br>";
echo "Database: " . $dbname . "<br>";
echo "Connection: " . ($conn->connect_error ? "❌ Failed: " . $conn->connect_error : "✅ Connected") . "<br>";

// Check tables
$tables = $conn->query("SHOW TABLES");
echo "<h3>Tables:</h3>";
while ($table = $tables->fetch_array()) {
    echo "• " . $table[0] . "<br>";
    
    // Count records
    $count = $conn->query("SELECT COUNT(*) as total FROM " . $table[0])->fetch_assoc()['total'];
    echo "&nbsp;&nbsp;Records: " . $count . "<br>";
}
?>