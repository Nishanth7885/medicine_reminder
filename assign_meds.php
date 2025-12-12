<?php include 'config.php'; 
$id = $_GET['id'] ?? 0;
if ($id == 0) die("Invalid patient ID.");
$patient = $conn->query("SELECT * FROM patients WHERE id=$id")->fetch_assoc();
if (!$patient) die("Patient not found.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Assign Meds to <?php echo $patient['name']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Assign Medicines to <?php echo $patient['name']; ?> (<?php echo $patient['ip_no']; ?>)</h2>
    
    <!-- Add Assignment Form -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col">
                <select name="medicine_id" class="form-control" required>
                    <option value="">Select Medicine</option>
                    <?php
                    $meds = $conn->query("SELECT * FROM medicines");
                    while ($m = $meds->fetch_assoc()) echo "<option value='{$m['id']}'>{$m['name']} ({$m['dosage']}, {$m['route']}, {$m['frequency']})</option>";
                    ?>
                </select>
            </div>
            <div class="col">
                <input type="text" name="times" class="form-control" placeholder="Times (comma-separated HH:MM, e.g., 08:00,14:00,20:00)" required>
            </div>
            <div class="col">
                <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
            </div>
            <div class="col">
                <input type="date" name="end_date" class="form-control" placeholder="End Date (optional)">
            </div>
            <div class="col-auto">
                <button type="submit" name="add" class="btn btn-primary">Assign</button>
            </div>
        </div>
    </form>
    
    <?php
    if (isset($_POST['add'])) {
        $medicine_id = $_POST['medicine_id'];
        $times_str = $_POST['times'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'] ?? null;
        $times = array_map('trim', explode(',', $times_str));
        foreach ($times as $t) {
            if (preg_match('/^\d{2}:\d{2}$/', $t)) {
                $time_full = $t . ':00';  // Add seconds
                $sql = "INSERT INTO schedules (patient_id, medicine_id, time, start_date, end_date) VALUES ($id, $medicine_id, '$time_full', '$start_date', " . ($end_date ? "'$end_date'" : 'NULL') . ")";
                $conn->query($sql);
            }
        }
        echo "<div class='alert alert-success'>Assigned. Added " . count($times) . " times.</div>";
    }
    
    // List Current Schedules
    $schedules = $conn->query("SELECT s.*, m.name as med_name, m.dosage, m.route, m.before_after_food FROM schedules s JOIN medicines m ON s.medicine_id = m.id WHERE s.patient_id = $id ORDER BY s.time");
    if ($schedules->num_rows > 0) {
        echo "<table class='table table-striped'><thead><tr><th>Medicine</th><th>Dosage/Route/Food</th><th>Time</th><th>Start/End</th><th>Actions</th></tr></thead><tbody>";
        while ($row = $schedules->fetch_assoc()) {
            $food = ucfirst($row['before_after_food']);
            $end = $row['end_date'] ?: 'Ongoing';
            echo "<tr><td>{$row['med_name']}</td><td>{$row['dosage']}, {$row['route']}, $food</td><td>{$row['time']}</td><td>{$row['start_date']} / $end</td><td>
                <form method='POST' style='display:inline;'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <button type='submit' name='delete' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete schedule?\")'>Remove</button>
                </form>
                </td></tr>";
        }
        echo "</tbody></table>";
    }
    
    <?php
    if (isset($_POST['delete'])) {
        $sch_id = $_POST['id'];
        $conn->query("DELETE FROM schedules WHERE id=$sch_id");
        echo "<div class='alert alert-success'>Removed.</div>";
        // Reload page
        header("Location: assign_meds.php?id=$id");
        exit;
    }
    ?>
    
    <a href="manage_patients.php" class="btn btn-secondary">Back to Patients</a>
    <a href="dashboard.php" class="btn btn-info">Dashboard</a>
</body>
</html>