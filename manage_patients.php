<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Patients</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Manage Patients</h2>
    
    <!-- Add Patient Form -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col">
                <input type="text" name="name" class="form-control" placeholder="Patient Name" required>
            </div>
            <div class="col">
                <select name="ward_id" class="form-control" required>
                    <option value="">Select Ward</option>
                    <?php
                    $wards = $conn->query("SELECT * FROM wards");
                    while ($w = $wards->fetch_assoc()) echo "<option value='{$w['id']}'>{$w['name']}</option>";
                    ?>
                </select>
            </div>
            <div class="col">
                <input type="text" name="ip_no" class="form-control" placeholder="IP No">
            </div>
            <div class="col">
                <input type="text" name="op_no" class="form-control" placeholder="OP No">
            </div>
            <div class="col-auto">
                <button type="submit" name="add" class="btn btn-primary">Add Patient</button>
            </div>
        </div>
    </form>
    
    <?php
    if (isset($_POST['add'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $ward_id = $_POST['ward_id'];
        $ip_no = $conn->real_escape_string($_POST['ip_no'] ?? '');
        $op_no = $conn->real_escape_string($_POST['op_no'] ?? '');
        $sql = "INSERT INTO patients (name, ward_id, ip_no, op_no) VALUES ('$name', $ward_id, '$ip_no', '$op_no')";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Patient added.</div>";
        else echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
    
    // List Patients (ward filter if selected)
    $ward_filter = isset($_GET['ward']) ? "WHERE p.ward_id = {$_GET['ward']}" : "";
    $result = $conn->query("SELECT p.*, w.name as ward_name FROM patients p JOIN wards w ON p.ward_id = w.id $ward_filter ORDER BY p.id");
    if ($result->num_rows > 0) {
        echo "<div class='mb-3'>
            <a href='?ward=0' class='btn btn-sm btn-outline-secondary'>All Wards</a>";
        $wards = $conn->query("SELECT * FROM wards");
        while ($w = $wards->fetch_assoc()) {
            echo " <a href='?ward={$w['id']}' class='btn btn-sm btn-outline-primary'>{$w['name']}</a>";
        }
        echo "</div>
        <table class='table table-striped'><thead><tr><th>ID</th><th>Name</th><th>Ward</th><th>IP No</th><th>OP No</th><th>Actions</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['ward_name']}</td><td>{$row['ip_no']}</td><td>{$row['op_no']}</td><td>
                <form method='POST' style='display:inline;'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <button type='submit' name='edit' class='btn btn-sm btn-warning'>Edit</button>
                    <button type='submit' name='delete' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Delete</button>
                    <a href='assign_meds.php?id={$row['id']}' class='btn btn-sm btn-success'>Assign Meds</a>
                </form>
                </td></tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<p>No patients found. <a href='?ward=0'>Show all</a></p>";
    }
    
    // Edit/Delete
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $result = $conn->query("SELECT * FROM patients WHERE id=$id");
        $row = $result->fetch_assoc();
        echo "<form method='POST' class='mt-3'>
            <input type='hidden' name='id' value='$id'>
            <input type='text' name='name' value='{$row['name']}' class='form-control mb-2' required>
            <select name='ward_id' class='form-control mb-2' required>
                <option value=''>Select Ward</option>";
        $wards = $conn->query("SELECT * FROM wards");
        while ($w = $wards->fetch_assoc()) {
            $sel = $w['id'] == $row['ward_id'] ? 'selected' : '';
            echo "<option value='{$w['id']}' $sel>{$w['name']}</option>";
        }
        echo "</select>
            <input type='text' name='ip_no' value='{$row['ip_no']}' class='form-control mb-2' placeholder='IP No'>
            <input type='text' name='op_no' value='{$row['op_no']}' class='form-control mb-2' placeholder='OP No'>
            <button type='submit' name='update' class='btn btn-success'>Update</button>
        </form>";
    }
    if (isset($_POST['update'])) {
        $id = $_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $ward_id = $_POST['ward_id'];
        $ip_no = $conn->real_escape_string($_POST['ip_no'] ?? '');
        $op_no = $conn->real_escape_string($_POST['op_no'] ?? '');
        $sql = "UPDATE patients SET name='$name', ward_id=$ward_id, ip_no='$ip_no', op_no='$op_no' WHERE id=$id";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Updated.</div>";
    }
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn->query("DELETE FROM patients WHERE id=$id");
        echo "<div class='alert alert-success'>Deleted.</div>";
    }
    ?>
    
    <a href="manage_wards.php" class="btn btn-secondary">Manage Wards</a>
    <a href="manage_medicines.php" class="btn btn-secondary">Manage Medicines</a>
    <a href="dashboard.php" class="btn btn-info">Dashboard</a>
</body>
</html>