<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Medicines</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Manage Medicines</h2>
    
    <!-- Add Medicine Form -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col">
                <input type="text" name="name" class="form-control" placeholder="Medicine Name" required>
            </div>
            <div class="col">
                <input type="text" name="dosage" class="form-control" placeholder="Dosage (e.g., 100mg)" required>
            </div>
            <div class="col">
                <input type="text" name="frequency" class="form-control" placeholder="Frequency (e.g., every 4 hours)">
            </div>
            <div class="col">
                <select name="route" class="form-control" required>
                    <option value="">Select Route</option>
                    <option value="oral">Oral</option>
                    <option value="IV">IV</option>
                    <option value="IM">IM</option>
                    <option value="SC">Subcutaneous</option>
                    <!-- Add more as per standards -->
                </select>
            </div>
            <div class="col">
                <select name="before_after_food" class="form-control" required>
                    <option value="any">Any</option>
                    <option value="before">Before Food</option>
                    <option value="after">After Food</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" name="add" class="btn btn-primary">Add Medicine</button>
            </div>
        </div>
    </form>
    
    <?php
    if (isset($_POST['add'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $dosage = $conn->real_escape_string($_POST['dosage']);
        $frequency = $conn->real_escape_string($_POST['frequency'] ?? '');
        $route = $_POST['route'];
        $before_after = $_POST['before_after_food'];
        $sql = "INSERT INTO medicines (name, dosage, frequency, route, before_after_food) VALUES ('$name', '$dosage', '$frequency', '$route', '$before_after')";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Medicine added.</div>";
        else echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
    
    // List Medicines
    $result = $conn->query("SELECT * FROM medicines ORDER BY id");
    if ($result->num_rows > 0) {
        echo "<table class='table table-striped'><thead><tr><th>ID</th><th>Name</th><th>Dosage</th><th>Frequency</th><th>Route</th><th>Food</th><th>Actions</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
            $food = ucfirst($row['before_after_food']);
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>{$row['dosage']}</td><td>{$row['frequency']}</td><td>{$row['route']}</td><td>$food</td><td>
                <form method='POST' style='display:inline;'>
                    <input type='hidden' name='id' value='{$row['id']}'>
                    <button type='submit' name='edit' class='btn btn-sm btn-warning'>Edit</button>
                    <button type='submit' name='delete' class='btn btn-sm btn-danger' onclick='return confirm(\"Delete?\")'>Delete</button>
                </form>
                </td></tr>";
        }
        echo "</tbody></table>";
    }
    
    // Edit/Delete
    if (isset($_POST['edit'])) {
        $id = $_POST['id'];
        $result = $conn->query("SELECT * FROM medicines WHERE id=$id");
        $row = $result->fetch_assoc();
        echo "<form method='POST' class='mt-3'>
            <input type='hidden' name='id' value='$id'>
            <input type='text' name='name' value='{$row['name']}' class='form-control mb-2' required>
            <input type='text' name='dosage' value='{$row['dosage']}' class='form-control mb-2' required>
            <input type='text' name='frequency' value='{$row['frequency']}' class='form-control mb-2'>
            <select name='route' class='form-control mb-2' required>
                <option value='oral' " . ($row['route']=='oral'?'selected':'') . ">Oral</option>
                <option value='IV' " . ($row['route']=='IV'?'selected':'') . ">IV</option>
                <option value='IM' " . ($row['route']=='IM'?'selected':'') . ">IM</option>
                <option value='SC' " . ($row['route']=='SC'?'selected':'') . ">Subcutaneous</option>
            </select>
            <select name='before_after_food' class='form-control mb-2' required>
                <option value='any' " . ($row['before_after_food']=='any'?'selected':'') . ">Any</option>
                <option value='before' " . ($row['before_after_food']=='before'?'selected':'') . ">Before Food</option>
                <option value='after' " . ($row['before_after_food']=='after'?'selected':'') . ">After Food</option>
            </select>
            <button type='submit' name='update' class='btn btn-success'>Update</button>
        </form>";
    }
    if (isset($_POST['update'])) {
        $id = $_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $dosage = $conn->real_escape_string($_POST['dosage']);
        $frequency = $conn->real_escape_string($_POST['frequency'] ?? '');
        $route = $_POST['route'];
        $before_after = $_POST['before_after_food'];
        $sql = "UPDATE medicines SET name='$name', dosage='$dosage', frequency='$frequency', route='$route', before_after_food='$before_after' WHERE id=$id";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Updated.</div>";
    }
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn->query("DELETE FROM medicines WHERE id=$id");
        echo "<div class='alert alert-success'>Deleted.</div>";
    }
    ?>
    
    <a href="manage_wards.php" class="btn btn-secondary">Manage Wards</a>
    <a href="manage_patients.php" class="btn btn-secondary">Manage Patients</a>
    <a href="dashboard.php" class="btn btn-info">Dashboard</a>
</body>
</html>