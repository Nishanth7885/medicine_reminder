<?php include 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Wards</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <h2>Manage Wards</h2>
    
    <!-- Add Ward Form -->
    <form method="POST" class="mb-4">
        <div class="row">
            <div class="col">
                <input type="text" name="name" class="form-control" placeholder="Ward Name (e.g., Ward 15)" required>
            </div>
            <div class="col-auto">
                <button type="submit" name="add" class="btn btn-primary">Add Ward</button>
            </div>
        </div>
    </form>
    
    <?php
    if (isset($_POST['add'])) {
        $name = $conn->real_escape_string($_POST['name']);
        $sql = "INSERT INTO wards (name) VALUES ('$name')";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Ward added.</div>";
        else echo "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
    }
    
    // List Wards
    $result = $conn->query("SELECT * FROM wards ORDER BY id");
    if ($result->num_rows > 0) {
        echo "<table class='table table-striped'><thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr><td>{$row['id']}</td><td>{$row['name']}</td><td>
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
        $result = $conn->query("SELECT * FROM wards WHERE id=$id");
        $row = $result->fetch_assoc();
        echo "<form method='POST' class='mt-3'>
            <input type='hidden' name='id' value='$id'>
            <input type='text' name='name' value='{$row['name']}' class='form-control' required>
            <button type='submit' name='update' class='btn btn-success mt-2'>Update</button>
        </form>";
    }
    if (isset($_POST['update'])) {
        $id = $_POST['id'];
        $name = $conn->real_escape_string($_POST['name']);
        $sql = "UPDATE wards SET name='$name' WHERE id=$id";
        if ($conn->query($sql)) echo "<div class='alert alert-success'>Updated.</div>";
    }
    if (isset($_POST['delete'])) {
        $id = $_POST['id'];
        $conn->query("DELETE FROM wards WHERE id=$id");
        echo "<div class='alert alert-success'>Deleted.</div>";
    }
    ?>
    
    <a href="manage_patients.php" class="btn btn-secondary">Manage Patients</a>
    <a href="manage_medicines.php" class="btn btn-secondary">Manage Medicines</a>
    <a href="dashboard.php" class="btn btn-info">Dashboard</a>
</body>
</html>