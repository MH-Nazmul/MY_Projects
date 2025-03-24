<?php
// Connect to the database
$conn = mysqli_connect("localhost", "root", "", "school_db");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Delete teacher
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "DELETE FROM teachers WHERE id=$id";
    if (mysqli_query($conn, $sql)) {
        header("Location: admin_dashboard.php");
    } else {
        echo "Error deleting record: " . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>