<?php
require_once 'config.php';

echo "<h2>Fixing Database Structure</h2>";

// Array of columns to add
$columns_to_add = [
    'admission_date' => "ALTER TABLE patients ADD COLUMN admission_date DATE DEFAULT NULL",
    'discharge_date' => "ALTER TABLE patients ADD COLUMN discharge_date DATE DEFAULT NULL",
    'discharge_notes' => "ALTER TABLE patients ADD COLUMN discharge_notes TEXT DEFAULT NULL"
];

foreach ($columns_to_add as $column_name => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "<p style='color: green;'>✓ Added column: $column_name</p>";
    } else {
        if (strpos($conn->error, 'Duplicate column name') !== false) {
            echo "<p style='color: orange;'>⚠ Column $column_name already exists - OK</p>";
        } else {
            echo "<p style='color: red;'>✗ Error with $column_name: " . $conn->error . "</p>";
        }
    }
}

echo "<br><h3>Checking current structure:</h3>";
$result = $conn->query("DESCRIBE patients");
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<br><p><a href='medicine_management.php'>Go to Medicine Management</a></p>";

$conn->close();
?>
